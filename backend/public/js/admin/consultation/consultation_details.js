document.addEventListener('DOMContentLoaded', function () {
    // Récupérer l'ID de la consultation depuis l'URL
    const urlParams = new URLSearchParams(window.location.search);
    const consultationId = urlParams.get('id') || window.location.pathname.split('/').pop();

    document.getElementById('btnPreviewFacture').addEventListener('click', function () {
        const factureContent = document.getElementById('factureContent');
        factureContent.style.display = 'block';

        // Fetch les données de la consultation
        fetch(`/api/admin/consultation/${consultationId}/details`)
            .then(response => response.json())
            .then(data => {
                const actes = data.actes;
                const consultation = data.consultation;
                const total = actes.reduce((sum, acte) => sum + (acte.prix * (acte.quantite || 1)), 0);
                const dateFacture = new Date().toLocaleDateString('fr-FR');

                factureContent.innerHTML = `
                    <div class="facture-container">
                        <header class="facture-header">
                            <div class="logo-container">
                                <img src="https://cdn.pixabay.com/photo/2017/01/08/21/11/medical-1964528_1280.png" class="facture-logo" alt="Logo Cabinet Médical">
                            </div>
                            <div class="entreprise-info">
                                <h2>Cabinet Dentaire Centre Dentaire Massaman</h2>
                                <p>Rue 403 - Porte 963 KalabanCoura ACI | Bamako-MALI</p>
                            </div>
                        </header>
                        <div class="client-info">
                            <p><strong>Facture à :</strong> ${consultation.patient.nom} ${consultation.patient.prenom}</p>
                            <p><strong>Contact :</strong> ${consultation.patient.telephone || 'Non renseigné'}</p>
                        </div>
                        <table class="facture-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Quantité</th>
                                    <th>Prix Unitaire</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${actes.map(acte => `
                                    <tr>
                                        <td>${acte.nom}${acte.description ? '<br><small>' + acte.description + '</small>' : ''}</td>
                                        <td>${acte.quantite || 1}</td>
                                        <td>${acte.prix.toLocaleString('fr-FR')} FCFA</td>
                                        <td>${(acte.prix * (acte.quantite || 1)).toLocaleString('fr-FR')} FCFA</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3">Total TTC</th>
                                    <th>${total.toLocaleString('fr-FR')} FCFA</th>
                                </tr>
                            </tfoot>
                        </table>
                        <footer class="facture-footer">
                            <p>"Une facture doit être réglée dans les 15 jours suivant son émission"</p>
                        </footer>
                    </div>
                `;
            })
            .catch(error => {
                console.error('Erreur lors de la récupération des détails de consultation:', error);
                factureContent.innerHTML = '<p>Erreur lors du chargement de la facture.</p>';
            });
    });
});