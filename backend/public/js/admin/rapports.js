document.addEventListener('DOMContentLoaded', function() {
  // Graphique : Répartition Homme/Femme (type pie)
  const ctxGender = document.getElementById('chartGender').getContext('2d');
  const chartGender = new Chart(ctxGender, {
    type: 'pie',
    data: {
      labels: ['Homme', 'Femme'],
      datasets: [{
        data: [55, 45],
        backgroundColor: ['#007bff', '#dc3545']
      }]
    },
    options: {
      responsive: true,
      animation: { duration: 1500 }
    }
  });

  // Graphique : Répartition par Tranche d'Âges (type bar)
  const ctxAge = document.getElementById('chartAge').getContext('2d');
  const chartAge = new Chart(ctxAge, {
    type: 'bar',
    data: {
      labels: ['0-18', '19-35', '36-50', '51+'],
      datasets: [{
        label: 'Nombre de patients',
        data: [40, 100, 80, 30],
        backgroundColor: ['#28a745','#17a2b8','#ffc107','#6c757d']
      }]
    },
    options: {
      responsive: true,
      animation: { duration: 1500 }
    }
  });

  // Graphique : Evolution des Patients & % de Retour (type line)
  const ctxPatientEvolution = document.getElementById('chartPatientEvolution').getContext('2d');
  const chartPatientEvolution = new Chart(ctxPatientEvolution, {
    type: 'line',
    data: {
      labels: ['S1', 'S2', 'S3', 'S4', 'S5'],
      datasets: [
        {
          label: 'Patients',
          data: [320, 330, 340, 345, 350],
          borderColor: '#6610f2',
          fill: false,
          tension: 0.4
        },
        {
          label: '% Retour',
          data: [20, 22, 21, 23, 25],
          borderColor: '#e83e8c',
          fill: false,
          tension: 0.4
        }
      ]
    },
    options: {
      responsive: true,
      animation: { duration: 1500 }
    }
  });

  // Graphique : Evolution Finance (Budget, Bénéfices, Dépenses) en ligne avec dégradés
  const ctxFinance = document.getElementById('chartFinance').getContext('2d');
  const gradientBudget = ctxFinance.createLinearGradient(0, 0, 0, 400);
  gradientBudget.addColorStop(0, 'rgba(0,123,255,0.5)');
  gradientBudget.addColorStop(1, 'rgba(0,123,255,0.1)');
  const gradientProfit = ctxFinance.createLinearGradient(0, 0, 0, 400);
  gradientProfit.addColorStop(0, 'rgba(40,167,69,0.5)');
  gradientProfit.addColorStop(1, 'rgba(40,167,69,0.1)');
  const gradientExpenses = ctxFinance.createLinearGradient(0, 0, 0, 400);
  gradientExpenses.addColorStop(0, 'rgba(220,53,69,0.5)');
  gradientExpenses.addColorStop(1, 'rgba(220,53,69,0.1)');

  const chartFinance = new Chart(ctxFinance, {
    type: 'line',
    data: {
      labels: ['Semaine 1', 'Semaine 2', 'Semaine 3', 'Semaine 4'],
      datasets: [
        {
          label: 'Budget',
          data: [500, 510, 520, 530],
          borderColor: '#007bff',
          backgroundColor: gradientBudget,
          fill: true,
          tension: 0.3
        },
        {
          label: 'Bénéfice',
          data: [50, 55, 60, 65],
          borderColor: '#28a745',
          backgroundColor: gradientProfit,
          fill: true,
          tension: 0.3
        },
        {
          label: 'Dépenses',
          data: [200, 210, 205, 215],
          borderColor: '#dc3545',
          backgroundColor: gradientExpenses,
          fill: true,
          tension: 0.3
        }
      ]
    },
    options: {
      responsive: true,
      animation: { duration: 1500 }
    }
  });

  // Graphique : Actes par Catégories (type bar)
  const ctxActs = document.getElementById('chartActs').getContext('2d');
  const chartActs = new Chart(ctxActs, {
    type: 'bar',
    data: {
      labels: ['Protèses', 'Soins dentaires', 'Détartrage', 'ODH', 'Extractions'],
      datasets: [{
        label: 'Nombre d\'actes',
        data: [30, 50, 20, 15, 10],
        backgroundColor: ['#007bff','#28a745','#ffc107','#17a2b8','#dc3545']
      }]
    },
    options: {
      responsive: true,
      animation: { duration: 1500 },
      plugins: { legend: { display: false } }
    }
  });
});