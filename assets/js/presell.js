document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('.offer-dialog');
    if (!dialog) return;
    try { localStorage.removeItem('presell_tracking'); localStorage.removeItem('presell_consent'); } catch (_) {}
    const keys = ['gclid', 'gbraid', 'wbraid', 'fbclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'subid', 'sub_id', 'source', 'network', 'value', 'campaignid', 'adgroupid', 'creative', 'keyword', 'matchtype', 'device', 'placement', 'targetid', 'loc_physical_ms'];
    document.querySelectorAll('[data-offer-choice]').forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault();
            const accepted = button.dataset.offerChoice === 'accepted';
            document.cookie = `presell_consent=${accepted ? 'accepted' : 'rejected'}; path=/; max-age=15552000; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
            const destination = new URL(accepted && dialog.dataset.bridge ? dialog.dataset.bridge : dialog.dataset.offerUrl, location.href);
            if (!['http:', 'https:'].includes(destination.protocol)) return;
            if (accepted) {
                const incoming = new URL(location.href).searchParams;
                keys.forEach(key => {
                    const value = incoming.get(key);
                    if (value && value.length <= 512 && !/[\x00-\x1f\x7f]/.test(value)) destination.searchParams.set(key, value);
                });
            }
            // A preview in the builder navigates only its iframe.
            location.assign(destination.href);
        });
    });
});
