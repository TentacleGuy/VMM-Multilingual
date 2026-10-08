document.addEventListener('change', event => {
    if (!event.target.matches('.vmm-group-language')) return;
    const group = event.target.closest('.vmm-language-group');
    group.querySelector('.vmm-group-label').textContent = (event.target.value === 'en_GB' ? 'Deutsch' : 'English') + '-Variante';
    const alternative = group.querySelector('.vmm-group-alternative');
    alternative.disabled = event.target.value === 'both';
    if (alternative.disabled) alternative.value = '';
});
