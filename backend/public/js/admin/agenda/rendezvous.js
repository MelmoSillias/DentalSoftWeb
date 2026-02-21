document.addEventListener('DOMContentLoaded', function () {
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const zoomDisplay = document.getElementById('zoomDisplay');
    const grid = document.getElementById('calendarGrid');

    let zoomLevel = 100; // pourcentage
    const minZoom = 50;
    const maxZoom = 150;
    const step = 10;

    function applyZoom() {
        grid.style.transform = `scale(${zoomLevel / 100})`;
        zoomDisplay.textContent = `${zoomLevel}%`;
    }

    zoomInBtn.addEventListener('click', function () {
        if (zoomLevel < maxZoom) {
            zoomLevel += step;
            applyZoom();
        }
    });

    zoomOutBtn.addEventListener('click', function () {
        if (zoomLevel > minZoom) {
            zoomLevel -= step;
            applyZoom();
        }
    });

    applyZoom(); // initial zoom
});

$(document).ready(function () {
    $('#view-nav-tab a').on('click', function (e) {
        e.preventDefault();
        $(this).tab('show');
    });
});