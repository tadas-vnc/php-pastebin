<?php
$isDark = isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'true';
?>
<script>
function toggleTheme() {
    const isDark = document.cookie.includes('darkMode=true');
    document.cookie = `darkMode=${!isDark}; path=/; max-age=31536000`;
    window.location.reload();
}
</script> 