document.addEventListener('DOMContentLoaded', () => {
    const banner = document.getElementById('consent-banner');
    const consent = document.cookie.split('; ').find(value => value.startsWith('presell_consent='))?.split('=')[1];
    // Remove legacy copies: the cookie is the single source of the current choice.
    try {
        localStorage.removeItem('presell_tracking');
        localStorage.removeItem('presell_consent');
    } catch (_) {}
    if (!['accepted', 'rejected'].includes(consent)) banner?.classList.remove('hidden');
    document.getElementById('consent-settings')?.addEventListener('click', () => banner?.classList.remove('hidden'));
    document.querySelectorAll('[data-consent]').forEach(button => {
        button.addEventListener('click', () => {
            const decision = button.dataset.consent === 'accepted' ? 'accepted' : 'rejected';
            document.cookie = `presell_consent=${decision}; path=/; max-age=15552000; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
            const url = new URL(location.href);
            if (decision === 'rejected') {
                const campaign = url.searchParams.get('campaign');
                url.search = '';
                if (campaign) url.searchParams.set('campaign', campaign);
            }
            location.replace(url.href);
        });
    });
});
