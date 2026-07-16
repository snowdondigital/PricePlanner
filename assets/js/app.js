document.addEventListener('DOMContentLoaded', () => {
  document.querySelector('.nav-toggle')?.addEventListener('click', () => document.querySelector('.topbar nav')?.classList.toggle('open'));
  document.querySelectorAll('[data-confirm]').forEach(el => el.addEventListener('click', event => {
    if (!confirm(el.dataset.confirm)) event.preventDefault();
  }));

  const bulkProductsForm = document.querySelector('#bulk-products-form');
  if (bulkProductsForm) {
    const selectAll = bulkProductsForm.querySelector('[data-select-all]');
    const boxes = [...bulkProductsForm.querySelectorAll('input[name="product_ids[]"]')];
    const count = bulkProductsForm.querySelector('[data-selection-count]');
    const syncSelection = () => {
      const selected = boxes.filter(box => box.checked).length;
      if (count) count.textContent = selected;
      if (selectAll) {
        selectAll.checked = boxes.length > 0 && selected === boxes.length;
        selectAll.indeterminate = selected > 0 && selected < boxes.length;
      }
    };
    selectAll?.addEventListener('change', () => {
      boxes.forEach(box => { box.checked = selectAll.checked; });
      syncSelection();
    });
    boxes.forEach(box => box.addEventListener('change', syncSelection));
    bulkProductsForm.querySelectorAll('[data-bulk-confirm]').forEach(button => button.addEventListener('click', event => {
      const selected = boxes.filter(box => box.checked).length;
      if (selected === 0 || !confirm(button.dataset.bulkConfirm.replace('the selected products', `${selected} selected product${selected === 1 ? '' : 's'}`))) event.preventDefault();
    }));
    syncSelection();
  }

  const empty = '-';
  const moneyFormat = value => value == null || !Number.isFinite(value) ? empty : new Intl.NumberFormat('en-GB', {style:'currency', currency:'GBP'}).format(value);
  const percentFormat = value => value == null || !Number.isFinite(value) ? empty : `${(value * 100).toFixed(1)}%`;

  const form = document.querySelector('#product-form');
  if (form) {
    const num = name => {
      const value = form.elements[name]?.value;
      return value === '' || value == null || Number.isNaN(Number(value)) ? null : Number(value);
    };
    const show = (key, value, formatter=moneyFormat) => {
      const el = form.querySelector(`[data-result="${key}"]`);
      if (el) el.textContent = formatter(value);
    };
    const calculate = () => {
      form.querySelectorAll('.percent-input').forEach(input => {
        form.elements[input.dataset.target].value = input.value === '' ? '' : Number(input.value) / 100;
      });
      const unit = num('unit_cost'), labour = num('labour_cost'), target = num('target_margin'), retail = num('retail_price'), discount = num('trade_discount'), trade = num('trade_price'), minimum = num('minimum_margin');
      const cost = unit == null || labour == null ? null : unit + labour;
      show('total_cost', cost);
      show('preferred_sell_price', cost == null || target == null || target >= 1 ? null : cost / (1 - target));
      show('retail_price_inc_vat', retail == null ? null : retail * (1 + Number(form.dataset.vat)));
      show('suggested_trade_price', retail == null || discount == null ? null : retail * (1 - discount));
      show('actual_trade_discount', retail == null || trade == null || retail === 0 ? null : 1 - trade / retail, percentFormat);
      show('retail_margin', retail == null || cost == null || retail === 0 ? null : (retail - cost) / retail, percentFormat);
      show('trade_margin', trade == null || cost == null || trade === 0 ? null : (trade - cost) / trade, percentFormat);
      show('minimum_price', cost == null || minimum == null || minimum >= 1 ? null : cost / (1 - minimum));
    };
    form.elements.target_margin_pct?.addEventListener('input', () => {
      if (form.elements.target_margin_changed) form.elements.target_margin_changed.value = '1';
    });
    form.elements.group_id?.addEventListener('change', () => {
      if (form.dataset.newProduct !== '1') return;
      if (form.elements.target_margin_changed?.value === '1') return;
      const margin = form.elements.group_id.selectedOptions[0]?.dataset.preferredMargin;
      if (margin === undefined || margin === '') return;
      form.elements.target_margin_pct.value = (Number(margin) * 100).toString();
      form.elements.target_margin.value = margin;
      calculate();
    });
    form.addEventListener('input', calculate);
    calculate();
  }

  const priceListForm = document.querySelector('#price-list-form');
  if (!priceListForm) return;
  const customToggle = priceListForm.querySelector('[data-toggle-custom-pricing]');
  const customPanel = priceListForm.querySelector('.custom-pricing-panel');
  const selectedRows = () => priceListForm.querySelectorAll('[data-price-list-row]');
  const globalDiscount = () => {
    const value = Number(priceListForm.elements.global_discount_pct?.value || 0);
    return Number.isFinite(value) ? Math.max(0, Math.min(99.99, value)) / 100 : 0;
  };
  const syncPriceList = () => {
    const enabled = customToggle?.checked;
    if (customPanel) customPanel.hidden = !enabled;
    if (priceListForm.elements.global_discount) priceListForm.elements.global_discount.value = enabled ? globalDiscount() : '';
    selectedRows().forEach(row => {
      const retail = row.dataset.retail === '' ? null : Number(row.dataset.retail);
      const cost = row.dataset.cost === '' ? null : Number(row.dataset.cost);
      const overrideInput = row.querySelector('.line-discount');
      if (overrideInput) overrideInput.disabled = !enabled;
      const override = overrideInput?.value === '' ? null : Number(overrideInput.value) / 100;
      const discount = enabled ? (override ?? globalDiscount()) : 0;
      const finalPrice = retail == null || !Number.isFinite(retail) ? null : retail * (1 - discount);
      const margin = finalPrice == null || cost == null || finalPrice === 0 ? null : (finalPrice - cost) / finalPrice;
      row.querySelector('[data-final-price]').textContent = moneyFormat(finalPrice);
      row.querySelector('[data-final-margin]').textContent = percentFormat(margin);
    });
  };
  priceListForm.querySelector('[data-select-products]')?.addEventListener('click', () => {
    const boxes = [...priceListForm.querySelectorAll('input[name="product_ids[]"]')];
    const shouldCheck = boxes.some(box => !box.checked);
    boxes.forEach(box => { box.checked = shouldCheck; });
  });
  priceListForm.querySelector('[data-product-filter]')?.addEventListener('input', event => {
    const query = event.target.value.trim().toLowerCase();
    selectedRows().forEach(row => {
      row.hidden = query !== '' && !row.dataset.filterText.includes(query);
    });
  });
  priceListForm.addEventListener('input', syncPriceList);
  priceListForm.addEventListener('change', syncPriceList);
  syncPriceList();
});
