import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

$('.btn-close').on('click', function () {
    $(this).closest('.modal').modal('hide');
});

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

// Notifications dropdown loader
document.addEventListener('DOMContentLoaded', () => {
    const badge = document.getElementById('notifBadge');
    const container = document.getElementById('notifItems');

    if (!badge || !container) {
        return;
    }

    const render = (items) => {
        container.innerHTML = '';
        if (!items.length) {
            container.innerHTML = '<div class="dropdown-item text-center small text-gray-500">Aucune notification</div>';
            badge.style.display = 'none';
            return;
        }

        let unread = 0;
        items.forEach((n) => {
            if (n.status === 'non_lu') unread += 1;
            const cls = n.status === 'non_lu' ? 'font-weight-bold' : '';
            const pri = n.priority === 'critique' ? 'text-danger' : n.priority === 'avertissement' ? 'text-warning' : 'text-info';
            const item = document.createElement('a');
            item.className = 'dropdown-item d-flex align-items-center ' + cls;
            item.href = n.goUrl;
            item.innerHTML = `
                <div class="mr-3">
                    <div class="icon-circle bg-light ${pri}"><i class="fas fa-bell"></i></div>
                </div>
                <div>
                    <div class="small text-gray-500">${n.createdAt ?? ''}</div>
                    <span>${n.message}</span>
                </div>`;
            container.appendChild(item);
        });

        badge.textContent = unread > 9 ? '9+' : unread;
        badge.style.display = unread > 0 ? 'inline-block' : 'none';
    };

    fetch('/notifications/latest')
        .then((r) => r.ok ? r.json() : [])
        .then(render)
        .catch(() => {
            container.innerHTML = '<div class="dropdown-item text-center small text-gray-500">Erreur de chargement</div>';
            badge.style.display = 'none';
        });
});
