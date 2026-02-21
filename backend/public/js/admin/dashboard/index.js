function openGlobalPrintModal() {
	$('#globalPrintModal').modal('show');
}

function printDoctorRow(btn) {
	const tr = $(btn).closest('tr');
	const row = $('#doctorsReport').DataTable().row(tr).data();
	const currentDate = new Date().toLocaleDateString('fr-FR');

	let html = `
		<html>
		<head>
			<title>Rapport Dr ${row.name}</title>
			<style>
				@page {
					size: A4 landscape;
					margin: 10mm;
				}
				body {
					font-family: "Times New Roman", serif;
					font-size: 12pt;
					line-height: 1.4;
					color: #000;
					margin: 0;
					padding: 10mm; 
				}
				.header {
					text-align: center;
					margin-bottom: 20px;
					border-bottom: 2px solid #000;
					padding-bottom: 10px;
				}
				.header h2 {
					margin: 0;
					font-size: 18pt;
					text-transform: uppercase;
				}
				.header p {
					margin: 5px 0 0;
					font-size: 11pt;
				}
				.clinic-info {
					font-size: 10pt;
					margin-bottom: 15px;
				}
				.doctor-info {
					margin-bottom: 20px;
				}
				.section-title {
					font-weight: bold;
					font-size: 13pt;
					margin: 25px 0 10px;
					border-bottom: 1px solid #ccc;
					padding-bottom: 3px;
				}
				table {
					width: 100%;
					border-collapse: collapse;
					margin: 15px 0; 
				}
				th {
					background-color: #f2f2f2;
					text-align: left;
					padding: 8px;
					border: 1px solid #ddd;
					font-weight: bold;
				}
				td {
					padding: 8px;
					border: 1px solid #ddd;
				}
				.signature-table {
					margin-top: 40px;
					width: 100%;
				}
				.signature-cell {
					width: 45%;
					text-align: center;
				}
				.signature-line {
					border-top: 1px solid #000;
					width: 80%;
					margin: 15px auto 5px;
				}
				.footer {
					font-size: 9pt;
					text-align: center;
					margin-top: 10px;
				}
			</style>
		</head>
		<body>
			<div class="header">
				<h2>Cabinet Dentaire Centre Dentaire Massaman</h2>
				<p>RAPPORT DE SERVICE MÉDICAL</p>
				<p class="clinic-info">
					Rue 404 - Porte 963 KalabanCoura ACI | Bamako-MALI | Tél: +223 44 54 26 09 / +223 97 08 12 92
				</p>
			</div>
			<div class="doctor-info">
				<div class="data-row" style="display: flex; justify-content: space-between;">
					<div>
						<strong>Médecin :</strong> Dr ${row.name}
					</div> 
				</div>
				<div>
					<strong>Période concernée :</strong>
					${$('#reportrange').text() !== "Choisir période" ? $('#reportrange').text() : '(non spécifiée)'}
				</div>
			</div>
			<div class="section-title">Statistiques d\'activité</div>
			${formatDoctorTable(row)}
			<div class="section-title">Détails des soins effectués par type</div>
			${formatActsDetails(row)}
			<table class="signature-table">
				<tr>
					<td class="signature-cell">
						<div class="signature-line"></div>
						<p>Signature du praticien</p>
					</td>
					<td class="signature-cell">
						<div class="signature-line"></div>
						<p>Cachet et visa de la direction</p>
					</td>
				</tr>
			</table>
			<div class="footer">
				Document généré automatiquement le ${currentDate} 
			</div>
		</body>
		</html>
	`;

	const printWindow = window.open('', '_blank');
	printWindow.document.write(html);
	printWindow.document.close();
	setTimeout(() => {
		printWindow.focus();
		printWindow.print();
		printWindow.close();
	}, 500);
}

function formatDoctorTable(row) {
	return `
		<table>
			<thead>
				<tr>
					<th>Indicateur</th>
					<th>Nombre</th>
					<th>Montant total</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>Consultations réalisées</td>
					<td>${row.consultations || '0'}</td>
					<td>${row.consultations_amount ? row.consultations_amount + ' F CFA' : '0 F CFA'}</td>
				</tr>
				<tr>
					<td>Actes posés</td>
					<td>${row.acts || '0'}</td>
					<td>${row.acts_amount ? row.acts_amount + ' F CFA' : '0 F CFA'}</td>
				</tr>
				<tr>
					<td colspan='2'>Apport total</td> 
					<td>${row.apport ? row.apport + ' F CFA' : '0 F CFA'}</td>
				</tr>
				<tr>
					<td colspan='2'>Montant Payé</td> 
					<td>${row.revenue ? row.revenue + ' F CFA' : '0 F CFA'}</td>
				</tr>
				<tr>
					<td colspan='2'>Reliquat Patients</td> 
					<td>${row.reliquat ? row.reliquat + ' F CFA' : '0 F CFA'}</td>
				</tr>
			</tbody>
		</table>
	`;
}

function formatActsDetails(row) {
	if (!row || !Array.isArray(row.paiements_period) || row.paiements_period.length === 0) {
		return '<div class="p-2"><em>Aucune entrée enregistrée sur cette période.</em></div>';
	}

	let detailsHtml = `
		<div class="p-3">
			<table class="table table-sm table-bordered mb-0">
				<thead class="thead-light">
					<tr>
						<th>Date</th>
						<th>Patient</th>
						<th>Description</th>
						<th>Montant</th>
					</tr>
				</thead>
				<tbody>`;

	let total = 0;
	row.paiements_period.forEach(d => {
		total += d.montant_paye;
		detailsHtml += `
			<tr>
				<td>${d.date}</td>
				<td>${d.patient}</td>
				<td>${d.description}</td>
				<td>${formatFcfa(d.montant_paye)}</td>
			</tr>`;
	});

	detailsHtml += `
			</tbody>
		</table>
		<p style="width: 500px; text-align: left; display:flex; flex-direction:row; align-items:center;">
			Total = <span style="margin-left:5px; font-weight:bold; font-size:20px">${formatFcfa(total)}</span>
		</p>
	</div>`;

	return detailsHtml;
}

function printDoctorsSummary() {
	$('#globalPrintModal').modal('hide');
	const table = $('#doctorsReport').DataTable();
	const rows = table.rows().data().toArray();
	let html = `
		<style>
			.print-header {
				text-align: center;
				margin-bottom: 20px;
				border-bottom: 2px solid #000;
				padding-bottom: 15px;
			}
			.print-header h2 {
				margin: 0;
				font-size: 18pt;
			}
		</style>
		<div style="font-family: Arial;">
			<div class="print-header">
				<h2>CABINET DENTAIRE Centre Dentaire Massaman</h2>
				<p><strong>Rapport de service (Résumé)</strong> - ${new Date().toLocaleDateString('fr-FR')}</p>
				<p class="clinic-info">
					Rue 404 - Porte 963 KalabanCoura ACI | Bamako-MALI | Tél: +223 44 54 26 09 / +223 97 08 12 92
				</p>
			</div>
			<p><strong>Période :</strong> ${$('#reportrange').text() || '(non spécifiée)'}</p>
			<table style="width: 100%; border-collapse: collapse;" border="1" cellpadding="5">
				<thead>
					<tr>
						<th>Médecin</th>
						<th>Consultations</th>
						<th>Montant généré (Fcfa)</th>
						<th>Montant payé (Fcfa)</th>
						<th>Réliquat patient</th>
						<th>Salaire</th> 
					</tr>
				</thead>
				<tbody>`;

	rows.forEach(r => {
		html += `
			<tr>
				<td>${r.name}</td>
				<td>${r.consultations} (${r.consultations_paid} payantes)</td>
				<td>${formatFcfa(r.apport)}</td> 
				<td>${formatFcfa(r.revenue)}</td>
				<td>${formatFcfa(r.reliquat)}</td>
				<td>${formatFcfa(r.salary)}</td>
			</tr>`;
	});

	html += `
			</tbody>
		</table>
		<br><br>
		<table style="width: 100%; margin-top: 50px;">
			<tr>
				<td style="text-align: left;"><strong>Signature Responsable</strong></td>
				<td style="text-align: right;"><strong>Cachet Cabinet</strong></td>
			</tr>
		</table>
	</div>`;

	printJS({
		printable: html,
		type: 'raw-html',
		style: '@page { size: A4 landscape; margin: 20mm; }',
		scanStyles: false
	});
}

function printAllActs() {
	$('#globalPrintModal').modal('hide');
	const rows = $('#doctorsReport').DataTable().rows().data().toArray();
	let allActes = [];

	rows.forEach(r => {
		if (Array.isArray(r.paiements_period)) {
			r.paiements_period.forEach(p => {
				allActes.push({
					date: p.date,
					medecin: p.medecin,
					patient: p.patient,
					description: p.description,
					montant_paye: p.montant_paye
				});
			});
		}
	});

	let html = `
		<style>
			.print-header {
				text-align: center;
				margin-bottom: 20px;
				border-bottom: 2px solid #000;
				padding-bottom: 15px;
			}
			.print-header h2 {
				margin: 0;
				font-size: 18pt;
			}
		</style>
		<div style="font-family: Arial;">
			<div class="print-header">
				<h2>CABINET DENTAIRE Centre Dentaire Massaman</h2>
				<p><strong>Rapport de service</strong> - ${new Date().toLocaleDateString('fr-FR')}</p>
				<p class="clinic-info">
				Rue 404 - Porte 963 KalabanCoura ACI | Bamako-MALI | Tél: +223 44 54 26 09 / +223 97 08 12 92
			</p>
		</div>
		<p><strong>Période :</strong> ${$('#reportrange').text() || '(non spécifiée)'}</p>
		<table style="width: 100%; border-collapse: collapse;" border="1" cellpadding="5">
			<thead>
				<tr>
					<th>Date</th>
					<th>Médecin</th>
					<th>Patient</th>
					<th>Description</th>
					<th>Montant</th>
				</tr>
			</thead>
			<tbody>`;

	let total = 0
	allActes.forEach(a => {
		html += `
		<tr>
			<td>${a.date}</td>
			<td>${a.medecin}</td>
			<td>${a.patient}</td>
			<td>${a.description}</td>
			<td>${formatFcfa(a.montant_paye)}</td>
		</tr>`;
		total += a.montant
	});

	html += `
			</tbody>
		</table>
		<br><br>
		<p style="width: 500px; text-align: left; display:flex; flex-direction:row; align-items:center;">
			Total = <span style="margin-left:5px; font-weight:bold; font-size:20px">${formatFcfa(total)}</span>
		</p>
		<br><br>
		<table style="width: 100%; margin-top: 50px;">
			<tr>
				<td style="text-align: left;"><strong>Signature Responsable</strong></td>
				<td style="text-align: right;"><strong>Cachet Cabinet</strong></td>
			</tr>
		</table>
	</div>`;

	printJS({
		printable: html,
		type: 'raw-html',
		style: '@page { size: A4 landscape; margin: 20mm; }',
		scanStyles: false
	});
}

function formatFcfa(amount) {
	return new Intl.NumberFormat('fr-FR').format(amount) + ' Fcfa';
}

function printSection(sectionId) {
	const section = document.getElementById(sectionId);
	if (!section) return;

	const printWindow = window.open('', '', 'height=842,width=1024');
	printWindow.document.write('<html><head><title>Impression</title>');
	printWindow.document.write('<style>');
	printWindow.document.write(`
		body {
			font-family: "Times New Roman", serif;
			font-size: 12pt;
			color: #000;
			margin: 0;
			padding: 20px;
		}
		.print-header {
			text-align: center;
			margin-bottom: 20px;
			border-bottom: 2px solid #000;
			padding-bottom: 15px;
		}
		.print-header h2 {
			margin: 0;
			font-size: 18pt;
		}
		.print-section-title {
			font-size: 14pt;
			font-weight: bold;
			margin: 25px 0 15px;
			border-bottom: 1px solid #ccc;
			padding-bottom: 5px;
		}
		.print-card {
			margin-bottom: 15px;
			page-break-inside: avoid;
		}
		.print-card-header {
			padding: 10px 15px;
			border-bottom: 1px solid #ddd;
		}
		.print-card-title {
			font-weight: bold;
			font-size: 12pt;
			margin: 0;
		}
		.print-list {
			list-style-type: none;
			padding: 0;
			margin: 0;
		}
		.print-list-item {
			display: flex;
			justify-content: space-between;
			padding: 8px 15px;
			border-bottom: 1px solid #eee;
		}
		.print-label {
			font-weight: bold;
		}
		.print-value {
			text-align: right;
		}
		.print-subvalue {
			font-size: 10pt;
			color: #666;
		}
		@page {
			size: A4;
			margin: 1.5cm;
		}
	`);
	printWindow.document.write('</style></head><body>');

	// Header
	printWindow.document.write(`
		<div class="print-header">
			<h2>CABINET DENTAIRE Centre Dentaire Massaman</h2>
			<p><strong>DOCUMENT ADMINISTRATIF</strong> - ${new Date().toLocaleDateString('fr-FR')}</p>
		</div>
	`);

	// Section title
	const sectionTitle = section.querySelector('.print-section-title, .print-card-title, h4, h6')?.innerText || 'Section';
	printWindow.document.write(`<div class="print-section-title">${sectionTitle}</div>`);

	// Section content
	printWindow.document.write(section.innerHTML);

	// Footer
	printWindow.document.write(`
		<div style="margin-top: 40px; padding-top: 15px; border-top: 1px solid #000; display: flex; justify-content: space-between;">
			<div style="width: 45%; text-align: center;">
				<hr style="border: none; border-top: 1px solid #000; width: 80%; margin: 15px auto 5px;">
				<p style="margin: 0; font-size: 10pt;">Signature du praticien</p>
			</div>
			<div style="width: 45%; text-align: center;">
				<hr style="border: none; border-top: 1px solid #000; width: 80%; margin: 15px auto 5px;">
				<p style="margin: 0; font-size: 10pt;">Cachet du cabinet</p>
			</div>
		</div>
	`);

	printWindow.document.write('</body></html>');
	printWindow.document.close();
	setTimeout(() => {
		printWindow.focus();
		printWindow.print();
		printWindow.close();
	}, 500);
}