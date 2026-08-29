document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#your-profile h2').forEach(function (h2) {
        if (h2.textContent.trim() === 'Personal Options') {
            var table = h2.nextElementSibling;
            h2.style.display = 'none';
            if (table && table.tagName === 'TABLE') {
                table.style.display = 'none';
            }
        }
    });
});
