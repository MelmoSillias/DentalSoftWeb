$(document).ready(function () {
    // Fetch les données des graphiques
    fetch('/api/finances/chart-data')
        .then(response => response.json())
        .then(data => {
            const months = data.months;
            const datasetsComptes = data.datasetsComptes;
            const barSoldeChart = data.barSoldeChart;
            const evolutionCapital = data.evolutionCapital;

            function hexToRgba(color, alpha) {
                const hex = color.startsWith('#') ? color.slice(1) : null;
                if (!hex)
                    return color;

                const full = hex.length === 3 ? hex.split('').map(c => c + c).join('') : hex;
                const num = parseInt(full, 16);
                const r = (num >> 16) & 255;
                const g = (num >> 8) & 255;
                const b = num & 255;
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            }

            function toAlpha(colorOrList, alpha) {
                if (Array.isArray(colorOrList)) {
                    return colorOrList.map(c => toAlpha(c, alpha));
                }
                if (typeof colorOrList === 'string') {
                    if (colorOrList.startsWith('#'))
                        return hexToRgba(colorOrList, alpha);

                    if (colorOrList.startsWith('rgb'))
                        return colorOrList.replace('rgb(', 'rgba(').replace(')', `, ${alpha})`);

                    if (colorOrList.startsWith('rgba('))
                        return colorOrList.replace(/rgba\(([^)]+),[^)]+\)/, `rgba($1, ${alpha})`);

                }
                return colorOrList;
            }

            function maxFromDatasets(datasets) {
                const values = [];
                datasets.forEach(ds => {
                    (ds.data || []).forEach(val => {
                        if (typeof val === 'number')
                            values.push(val);
                        else if (val && typeof val === 'object' && 'y' in val)
                            values.push(val.y);
                    });
                });
                return values.length ? Math.max(...values) : 0;
            }

            $('#transactionsTable').DataTable({
                language: {
                    url: '/js/utils/datatables_fr.json'
                },
                order: [
                    [0, 'desc']
                ]
            });

            $('#interCompteBtn').on('click', function () {
                $.get('/api/payment-methods', function (data) {
                    const $from = $('#fromAccount');
                    const $to = $('#toAccount');

                    $from.empty();
                    $to.empty();

                    data.forEach(mode => {
                        if (mode.actif) {
                            const option = `<option value="${mode.id}">${mode.libelle} (${mode.type})</option>`;
                            $from.append(option);
                            $to.append(option);
                        }
                    });

                    // Filtrage automatique des options identiques
                    handleCompteFilter();

                    $('#interCompteModal').modal('show');
                });
            });

            function handleCompteFilter() {
                $('#fromAccount').on('change', function () {
                    const selected = $(this).val();
                    $('#toAccount option').each(function () {
                        $(this).prop('disabled', $(this).val() === selected);
                    });

                    // Auto-sélection du premier compte valide
                    const $to = $('#toAccount');
                    if ($to.val() === selected) {
                        const firstOther = $to.find('option:not([disabled])').first().val();
                        $to.val(firstOther);
                    }
                });

                $('#toAccount').on('change', function () {
                    const selected = $(this).val();
                    $('#fromAccount option').each(function () {
                        $(this).prop('disabled', $(this).val() === selected);
                    });

                    const $from = $('#fromAccount');
                    if ($from.val() === selected) {
                        const firstOther = $from.find('option:not([disabled])').first().val();
                        $from.val(firstOther);
                    }
                });

                // Déclenche une fois pour l'initialisation
                $('#fromAccount').trigger('change');
            }

            const ctx = $('#financeChart')[0].getContext('2d');
            const financeDatasets = datasetsComptes.map(ds => ({
                ...ds,
                backgroundColor: toAlpha(ds.backgroundColor || ds.borderColor || '#0d6efd', 0.35),
                borderColor: ds.borderColor || ds.backgroundColor || '#0d6efd',
                borderWidth: 2
            }));
            const financeMax = maxFromDatasets(financeDatasets) * 1.15;

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: financeDatasets
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Évolution mensuelle des Entrées / Dépenses / Soldes par compte',
                            font: {
                                size: 18
                            }
                        }
                    },
                    animation: {
                        duration: 900,
                        easing: 'easeOutQuart'
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMax: financeMax || undefined,
                            ticks: {
                                maxTicksLimit: 7,
                                callback: val => new Intl.NumberFormat('fr-FR', {
                                    style: 'currency',
                                    currency: 'XOF'
                                }).format(val)
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 0
                            }
                        }
                    }
                }
            });

            const $legend = $('#customLegend');
            const used = new Set();
            $legend.html('<table class="table table-bordered table-sm"><thead><tr><th>Compte</th><th>Couleur</th></tr></thead><tbody></tbody></table>');

            chart.data.datasets.forEach(ds => {
                const baseLabel = ds.label.split(' - ')[0];
                if (!used.has(baseLabel)) {
                    used.add(baseLabel);
                    $('#customLegend tbody').append(`
                        <tr>
                            <td>${baseLabel}</td>
                            <td><div style="width:30px;height:14px;background:${ds.borderColor ?? ds.backgroundColor};border:1px solid #ccc;"></div></td>
                        </tr>
                    `);
                }
            });

            const dataBarSolde = {
                labels: barSoldeChart.labels,
                datasets: [
                    {
                        label: 'Entrées',
                        data: barSoldeChart.entrees,
                        backgroundColor: 'rgba(40, 167, 69, 0.55)',
                        borderColor: 'rgba(40, 167, 69, 0.9)',
                        borderWidth: 1.5,
                        stack: 'group1',
                        type: 'bar'
                    }, {
                        label: 'Dépenses',
                        data: barSoldeChart.depenses,
                        backgroundColor: 'rgba(220, 53, 69, 0.55)',
                        borderColor: 'rgba(220, 53, 69, 0.9)',
                        borderWidth: 1.5,
                        stack: 'group1',
                        type: 'bar'
                    }, {
                        label: 'Solde',
                        data: barSoldeChart.soldes,
                        type: 'bubble',
                        pointStyle: 'circle',
                        backgroundColor: toAlpha(barSoldeChart.colors, 0.6),
                        borderColor: toAlpha(barSoldeChart.colors, 0.9),
                        pointRadius: 9,
                        pointHoverRadius: 12,
                        showLine: false,
                        parsing: {
                            xAxisKey: 'x',
                            yAxisKey: 'y'
                        }
                    }
                ]
            };

            const ctxSolde = document.getElementById('barSoldeChart').getContext('2d');
            const barSoldeMax = maxFromDatasets(dataBarSolde.datasets) * 1.15;
            new Chart(ctxSolde, {
                type: 'bar',
                data: {
                    labels: dataBarSolde.labels,
                    datasets: [
                        dataBarSolde.datasets[0], dataBarSolde.datasets[1], {
                            label: 'Solde',
                            data: dataBarSolde.labels.map((_, i) => ({x: i, y: dataBarSolde.datasets[2].data[i]})),
                            type: 'line',
                            showLine: false,
                            pointStyle: 'circle',
                            backgroundColor: dataBarSolde.datasets[2].backgroundColor,
                            pointRadius: 9,
                            pointHoverRadius: 11
                        }
                    ]
                },
                options: {
                    plugins: {
                        title: {
                            display: true,
                            text: 'Entrées / Dépenses avec points de solde par compte'
                        },
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 16
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => {
                                    const val = ctx.parsed.y ?? ctx.raw.y;
                                    return `${ctx.dataset.label}: ` + new Intl.NumberFormat('fr-FR', {
                                        style: 'currency',
                                        currency: 'XOF'
                                    }).format(val);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMax: barSoldeMax || undefined,
                            ticks: {
                                maxTicksLimit: 7,
                                callback: val => new Intl.NumberFormat('fr-FR', {
                                    style: 'currency',
                                    currency: 'XOF'
                                }).format(val)
                            }
                        },
                        x: {
                            stacked: true
                        }
                    }
                }
            });

            const evolutionCtx = document.getElementById('evolutionCapitalChart').getContext('2d');
            const evolutionMax = Math.max(...evolutionCapital, 0) * 1.15;
            new Chart(evolutionCtx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Évolution du capital',
                            data: evolutionCapital,
                            backgroundColor: 'rgba(0,123,255,0.15)',
                            borderColor: 'rgba(0,123,255,0.85)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }
                    ]
                },
                options: {
                    plugins: {
                        title: {
                            display: true,
                            text: 'Capital cumulé mensuel'
                        },
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMax: evolutionMax || undefined,
                            ticks: {
                                maxTicksLimit: 7,
                                callback: val => new Intl.NumberFormat('fr-FR', {
                                    style: 'currency',
                                    currency: 'XOF'
                                }).format(val)
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 0
                            }
                        }
                    }
                }
            });

            $('#transactionForm').on('submit', function (event) {
                event.preventDefault();

                const formData = $(this).serialize();
                $.ajax({
                    url: '/api/transaction',
                    method: 'POST',
                    data: formData,
                    success: function (response) {
                        alert(response.message);
                        location.reload();
                    },
                    error: function () {
                        alert('Une erreur est survenue.');
                    }
                });
            });

            $('#daterange').daterangepicker({
                locale: {
                    format: 'YYYY-MM-DD',
                    applyLabel: 'Appliquer',
                    cancelLabel: 'Annuler',
                    fromLabel: 'De',
                    toLabel: 'À',
                    customRangeLabel: 'Personnalisé',
                    weekLabel: 'S',
                    daysOfWeek: [
                        'Di',
                        'Lu',
                        'Ma',
                        'Me',
                        'Je',
                        'Ve',
                        'Sa'
                    ],
                    monthNames: [
                        'Janvier',
                        'Février',
                        'Mars',
                        'Avril',
                        'Mai',
                        'Juin',
                        'Juillet',
                        'Août',
                        'Septembre',
                        'Octobre',
                        'Novembre',
                        'Décembre'
                    ],
                    firstDay: 1
                },
                opens: 'left',
                alwaysShowCalendars: true,
                ranges: {
                    "Aujourd'hui": [
                        moment(), moment()
                    ],
                    "Hier": [
                        moment().subtract(1, 'days'),
                        moment().subtract(1, 'days')
                    ],
                    "Cette semaine": [
                        moment().startOf('week'), moment().endOf('week')
                    ],
                    "Ce mois-ci": [
                        moment().startOf('month'), moment().endOf('month')
                    ],
                    "Cette année": [moment().startOf('year'), moment().endOf('year')]
                }
            }, function (start, end) {
                fetchTransactions(start.format('YYYY-MM-DD'), end.format('YYYY-MM-DD'));
            });

            function fetchTransactions(start, end) {
                $.ajax({
                    url: '/api/transactions',
                    method: 'GET',
                    data: {
                        start,
                        end
                    },
                    success: function (data) {
                        const tbody = $('#transactionsTable tbody');
                        tbody.empty();
                        if (data.length === 0) {
                            tbody.append('<tr><td colspan="4" class="text-center">Aucune transaction trouvée pour cette période.</td></tr>');
                        } else {
                            data.forEach(transaction => {
                                tbody.append(`
                                    <tr>
                                        <td>${transaction.date}</td>
                                        <td>${transaction.description}</td>
                                        <td>
                                            <span class="${transaction.type == 'Entrée' ? 'text-success' : 'text-danger'}">
                                                ${transaction.type == 'Entrée' ? 'Entrée' : 'Sortie'}
                                            </span>
                                        </td>
                                        <td>${transaction.amount}</td>
                                    </tr>
                                `);
                            });
                        }
                    },
                    error: function () {
                        alert('Erreur lors de la récupération des transactions.');
                    }
                });
            }
        })
        .catch(error => {
            console.error('Erreur lors de la récupération des données financières:', error);
        });
});