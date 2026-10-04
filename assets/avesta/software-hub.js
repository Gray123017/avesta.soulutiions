(() => {
  const products = document.querySelectorAll('[data-product-card]');
  products.forEach((card) => {
    card.addEventListener('click', (event) => {
      if (event.target.closest('a,button')) return;
      const target = card.dataset.productHref;
      if (target) window.location.href = target;
    });
    card.addEventListener('keydown', (event) => {
      if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a,button')) {
        event.preventDefault();
        const target = card.dataset.productHref;
        if (target) window.location.href = target;
      }
    });
  });
})();