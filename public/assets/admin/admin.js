(function () {
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.querySelector('[data-nav]');
    var backdrop = document.querySelector('[data-nav-backdrop]');
    var loader = document.querySelector('[data-page-loading]');

    function setOpen(open) {
        if (!nav || !toggle) {
            return;
        }

        nav.classList.toggle('is-open', open);
        document.body.classList.toggle('nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (backdrop) {
            backdrop.hidden = !open;
        }
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setOpen(false);
        });
    }

    if (nav) {
        nav.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                setOpen(false);
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a');

        if (!loader || !link || link.target === '_blank') {
            return;
        }

        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        if (link.origin !== window.location.origin) {
            return;
        }

        loader.hidden = false;
        document.body.setAttribute('aria-busy', 'true');
    });
}());
