<?php
declare(strict_types=1);

function app_version(): string
{
    $version = @file_get_contents(dirname(__DIR__) . '/VERSION');
    return trim((string)$version) ?: '0.0.0';
}

function update_request(string $url): string
{
    $headers = ['Accept: application/vnd.github+json', 'User-Agent: PricePlan/' . app_version()];
    $timeout = (int)config('updates.timeout', 20);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout, CURLOPT_TIMEOUT => $timeout, CURLOPT_HTTPHEADER => $headers]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status < 200 || $status >= 300) {
            throw new RuntimeException($error ?: "GitHub returned HTTP $status.");
        }
        return (string)$body;
    }
    $context = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers),
        'timeout' => $timeout, 'follow_location' => 1, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) throw new RuntimeException('Unable to connect to GitHub. Enable cURL or URL file access.');
    return $body;
}

function latest_release(bool $refresh = false): array
{
    if (!config('updates.enabled', false)) throw new RuntimeException('Updates are disabled in config/config.php.');
    $repo = (string)config('updates.repository');
    if (!preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', $repo)) throw new RuntimeException('The update repository is invalid.');
    $cache = dirname(__DIR__) . '/storage/updates/release.json';
    if (!$refresh && is_file($cache) && filemtime($cache) > time() - 3600) {
        $release = json_decode((string)file_get_contents($cache), true);
        if (is_array($release)) return $release;
    }
    $release = json_decode(update_request("https://api.github.com/repos/$repo/releases/latest"), true);
    if (!is_array($release) || empty($release['tag_name'])) throw new RuntimeException('GitHub returned an invalid release.');
    @mkdir(dirname($cache), 0750, true);
    @file_put_contents($cache, json_encode($release, JSON_PRETTY_PRINT), LOCK_EX);
    return $release;
}

function release_asset(array $release): array
{
    $wanted = (string)config('updates.asset', 'priceplan-update.zip');
    foreach (($release['assets'] ?? []) as $asset) {
        if (($asset['name'] ?? '') === $wanted && !empty($asset['browser_download_url'])) return $asset;
    }
    throw new RuntimeException("Release asset $wanted was not found.");
}

function update_copy_tree(string $source, string $target, array $preserve = []): void
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));
        if (in_array($relative, $preserve, true) || str_starts_with($relative, 'storage/')) continue;
        $destination = $target . '/' . $relative;
        if ($item->isDir()) {
            if (!is_dir($destination) && !mkdir($destination, 0755, true)) throw new RuntimeException("Could not create $relative.");
        } else {
            if (!is_dir(dirname($destination))) mkdir(dirname($destination), 0755, true);
            if (!copy($item->getPathname(), $destination)) throw new RuntimeException("Could not write $relative.");
        }
    }
}

function update_backup_files(string $stage, string $root, string $backup): array
{
    $created = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        if (!$item->isFile()) continue;
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($stage) + 1));
        if ($relative === 'config/config.php' || str_starts_with($relative, 'storage/')) continue;
        $current = $root . '/' . $relative;
        if (!is_file($current)) { $created[] = $current; continue; }
        $destination = $backup . '/' . $relative;
        if (!is_dir(dirname($destination))) mkdir(dirname($destination), 0750, true);
        if (!copy($current, $destination)) throw new RuntimeException("Could not back up $relative.");
    }
    return $created;
}

function install_release(array $release): string
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('The PHP Zip extension is required to install updates.');
    $version = ltrim((string)$release['tag_name'], 'vV');
    if (!version_compare($version, app_version(), '>')) throw new RuntimeException('This release is not newer than the installed version.');
    $asset = release_asset($release);
    $root = dirname(__DIR__);
    $work = $root . '/storage/updates/' . preg_replace('/[^0-9A-Za-z._-]/', '-', $version);
    $zipFile = $work . '/update.zip';
    $stage = $work . '/stage';
    if (!is_dir($work) && !mkdir($work, 0750, true)) throw new RuntimeException('The update workspace is not writable.');
    $package = update_request((string)$asset['browser_download_url']);
    if (file_put_contents($zipFile, $package, LOCK_EX) === false) throw new RuntimeException('Could not save the update package.');
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) throw new RuntimeException('The release package is not a valid ZIP file.');
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', $zip->getNameIndex($i));
        if (str_contains($name, '../') || str_starts_with($name, '/')) { $zip->close(); throw new RuntimeException('The release package contains an unsafe path.'); }
    }
    if (!is_dir($stage)) mkdir($stage, 0750, true);
    if (!$zip->extractTo($stage)) { $zip->close(); throw new RuntimeException('Could not extract the release package.'); }
    $zip->close();
    $manifest = json_decode((string)@file_get_contents($stage . '/update.json'), true);
    if (!is_array($manifest) || ($manifest['version'] ?? '') !== $version || trim((string)@file_get_contents($stage . '/VERSION')) !== $version) {
        throw new RuntimeException('The package version does not match the GitHub release.');
    }
    $migrations = [];
    foreach (($manifest['migrations'] ?? []) as $migration) {
        if (!is_string($migration) || !preg_match('~^sql/[A-Za-z0-9._-]+\.sql$~', $migration)) throw new RuntimeException('The update manifest contains an invalid migration.');
        $sql = @file_get_contents($stage . '/' . $migration);
        if ($sql === false) throw new RuntimeException("Migration $migration is missing.");
        $migrations[] = [$migration, $sql];
    }
    $backup = $work . '/backup';
    if (!is_dir($backup)) mkdir($backup, 0750, true);
    $created = update_backup_files($stage, $root, $backup);
    try {
        update_copy_tree($stage, $root, ['config/config.php']);
        foreach ($migrations as [$migration, $sql]) db()->exec($sql);
    } catch (Throwable $exception) {
        update_copy_tree($backup, $root);
        foreach ($created as $file) if (is_file($file)) @unlink($file);
        throw new RuntimeException('The update failed and application files were restored: ' . $exception->getMessage(), 0, $exception);
    }
    @unlink(dirname($work) . '/release.json');
    return $version;
}
