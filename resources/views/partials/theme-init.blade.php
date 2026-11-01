<script>
    try {
        document.documentElement.dataset.theme = localStorage.getItem('it-helpdesk-theme') === 'dark' ? 'dark' : 'light';
    } catch {
        document.documentElement.dataset.theme = 'light';
    }
    document.documentElement.classList.toggle('dark', document.documentElement.dataset.theme === 'dark');
</script>