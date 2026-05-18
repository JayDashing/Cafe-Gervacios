<script>
    (function () {
        var KEY = 'tc_seat_focus_mode';
        var btn = document.getElementById('tc-seat-focus-toggle');
        if (!btn) return;
        var icon = btn.querySelector('i');
        var label = btn.querySelector('.tc-seat-focus-toggle__label');

        function apply(on) {
            document.documentElement.classList.toggle('tc-seat-focus-mode', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.title = on ? 'Exit focus mode (show navigation, header, and tools)' : 'Focus mode (hide navigation, header, and extra tools)';
            if (label) {
                label.textContent = on ? 'Exit focus' : 'Focus';
            }
            if (icon) {
                icon.classList.toggle('fa-expand', !on);
                icon.classList.toggle('fa-compress', on);
            }
            try {
                localStorage.setItem(KEY, on ? '1' : '0');
            } catch (e) {}
            window.dispatchEvent(new Event('resize'));
            if (typeof window.Livewire !== 'undefined' && typeof window.Livewire.dispatch === 'function') {
                window.Livewire.dispatch('tables-refresh');
                window.Livewire.dispatch('table-updated');
            }
            setTimeout(function () {
                window.dispatchEvent(new Event('resize'));
            }, 100);
        }

        btn.addEventListener('click', function () {
            apply(!document.documentElement.classList.contains('tc-seat-focus-mode'));
        });

        try {
            apply(localStorage.getItem(KEY) === '1');
        } catch (e2) {
            apply(false);
        }
    })();
</script>
