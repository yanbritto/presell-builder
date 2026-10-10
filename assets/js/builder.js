document.getElementById('link-builder-form')?.addEventListener('submit', () => {
    const button = document.getElementById('generate-button');
    button.disabled = true;
    button.textContent = 'Lendo a oferta e gerando sua página…';
});
