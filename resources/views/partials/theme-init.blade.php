<link
    rel="icon"
    type="image/png"
    id="app-favicon"
    data-light-href="{{ asset('images/rirs_logo_enhanced.png') }}"
    data-dark-href="{{ asset('images/rirs_logo_transparent.png') }}"
    href="{{ asset('images/rirs_logo_enhanced.png') }}"
>
<script>
    try {
        document.documentElement.dataset.theme = localStorage.getItem('it-helpdesk-theme') === 'dark' ? 'dark' : 'light';
    } catch {
        document.documentElement.dataset.theme = 'light';
    }
    document.documentElement.classList.toggle('dark', document.documentElement.dataset.theme === 'dark');
    const favicon = document.getElementById('app-favicon');
    favicon.href = document.documentElement.dataset.theme === 'dark' ? favicon.dataset.darkHref : favicon.dataset.lightHref;
</script>