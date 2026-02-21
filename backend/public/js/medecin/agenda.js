document.addEventListener('DOMContentLoaded', () => {
    const daySlider = document.getElementById('day-slider');
    const prevDayButton = document.getElementById('prev-day');
    const nextDayButton = document.getElementById('next-day');
    const todayButton = document.getElementById('today-btn');
    const datePicker = document.getElementById('date-picker');
    const statsContent = document.getElementById('stats-content');
    const calendar = document.getElementById('calendar');
    let currentDate = new Date();
    let autoScroll = true;

    function formatDate(date) {
        return new Intl.DateTimeFormat('fr-FR', {
            weekday: 'short',
            day: 'numeric',
            month: 'short'
        }).format(date);
    }

    function generateDays() {
        daySlider.innerHTML = '';
        const days = [];

        for (let i = -7; i <= 7; i++) {
            const day = new Date(currentDate);
            day.setDate(currentDate.getDate() + i);
            days.push(day);
        }

        days.forEach(day => {
            const dayElement = document.createElement('div');
            dayElement.className = 'day-item text-center px-3 py-2';
            dayElement.innerHTML = `
                <div class="fw-bold">${formatDate(day)}</div>
                <div class="text-muted">${day.getDate()}</div>
            `;
            dayElement.dataset.date = day.toISOString().split('T')[0];

            if (day.toDateString() === currentDate.toDateString()) {
                dayElement.classList.add('active');
            }

            dayElement.addEventListener('click', () => {
                currentDate = new Date(day);
                updateActiveDay();
                reloadRdvs();
                centerActiveDay();
            });

            daySlider.appendChild(dayElement);
        });

        if (autoScroll) centerActiveDay();
    }

    function centerActiveDay() {
        const activeDay = daySlider.querySelector('.day-item.active');
        if (activeDay) {
            const sliderWidth = daySlider.offsetWidth;
            const scrollPos = activeDay.offsetLeft - (sliderWidth / 2) + (activeDay.offsetWidth / 2);
            daySlider.scrollTo({
                left: scrollPos,
                behavior: 'smooth'
            });
        }
    }

    function updateActiveDay() {
        daySlider.querySelectorAll('.day-item').forEach(item => {
            item.classList.remove('active');
            if (new Date(item.dataset.date).toDateString() === currentDate.toDateString()) {
                item.classList.add('active');
            }
        });
    }

    function loadStats() {
        statsContent.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        `;

        setTimeout(() => {
            statsContent.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Rendez-vous</h5>
                                <p class="display-4">12</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Heures</h5>
                                <p class="display-4">8h</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Salles</h5>
                                <p class="display-4">${document.querySelectorAll('.room-header').length}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }, 500);
    }

    function reloadRdvs() {
        const url = `/api/rdvs/${currentDate.toISOString().split('T')[0]}`;
        console.log(url);

        fetch(url)
            .then(response => response.json())
            .then(data => {
                document.querySelectorAll('.room-cell').forEach(cell => {
                    cell.innerHTML = ''; // Vider les cellules avant de recharger
                });

                data.forEach(rdv => {
                    const cell = document.querySelector(
                        `.room-cell[data-hour="${new Date(rdv.dateRdv).getHours()}"][data-minute="${('0' + new Date(rdv.dateRdv).getMinutes()).slice(-2)}"][data-salle="${rdv.salle}"]`
                    );

                    if (cell) {
                        const rdvElement = document.createElement('div');
                        rdvElement.className = 'rdv-card mx-2 my-2';
                        const stateColors = {
                            0: 'bg-warning',
                            1: 'bg-success',
                            '-1': 'bg-secondary',
                            '-2': 'bg-danger'
                        };

                        const stateLabels = {
                            0: 'En attente',
                            1: 'Validé',
                            '-1': 'Reporté',
                            '-2': 'Annulé'
                        };

                        rdvElement.innerHTML = `
                            <div class="card shadow-sm">
                                <div class="d-flex">
                                    <div class="state-indicator ${stateColors[rdv.statut]}" style="width: 5px;"></div>
                                    <div class="card-body">
                                        <p class="card-text"><strong>Patient :</strong> ${rdv.patient} </p>
                                        <p class="card-text"><strong>Médecin :</strong> ${rdv.medecin}</p>
                                        <p class="card-text"><strong>Pris le :</strong> ${rdv.dateCreation}</p>
                                        <p class="card-text"><strong>État :</strong> ${stateLabels[rdv.statut]}</p>
                                    </div>
                                </div>
                            </div>
                        `;

                        cell.appendChild(rdvElement);
                        cell.style.height = "auto";
                    }
                });
            });
    }

    prevDayButton.addEventListener('click', () => {
        autoScroll = false;
        currentDate.setDate(currentDate.getDate() - 1);
        generateDays();
        reloadRdvs();
    });

    nextDayButton.addEventListener('click', () => {
        autoScroll = false;
        currentDate.setDate(currentDate.getDate() + 1);
        generateDays();
        reloadRdvs();
    });

    todayButton.addEventListener('click', () => {
        currentDate = new Date();
        autoScroll = true;
        generateDays();
        reloadRdvs();
    });

    datePicker.addEventListener('change', (event) => {
        const selectedDate = new Date(event.target.value);
        if (!isNaN(selectedDate)) {
            currentDate = selectedDate;
            autoScroll = true;
            generateDays();
            reloadRdvs();
        }
    });

    generateDays();
    loadStats();
    reloadRdvs();
});
