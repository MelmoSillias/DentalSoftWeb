$('.btn-close').on('click', function () {
	const modal = $(this).closest('.modal');
	if (modal.length) {
		modal.modal('hide');
	}
});

let pondInstance;

FilePond.registerPlugin(FilePondPluginFileValidateType, FilePondPluginFileValidateSize);

pondInstance = FilePond.create(document.querySelector('input.filepond'), {
		allowMultiple: true,
		storeAsFile: true,
		maxFileSize: '5MB',
		acceptedFileTypes: [
	'application/pdf', 'image/*'
		],
		labelIdle: `Glissez‑déposez vos fichiers ou <span class="filepond--label-action">Parcourir</span>`,
		server: {
			process: (fieldName, file, metadata, load, error, progress, abort) => { // Simulation d'upload avec progression
			const total = file.size;
			let loaded = 0;
			const speed = total / 20; // 20 étapes de progression
			const timer = setInterval(() => {
			loaded = Math.min(loaded + speed, total);
			// Mettre à jour la progression
			progress(true, loaded, total);
			if (loaded >= total) {
			clearInterval(timer);
			// Appel load quand terminé (fournit un identifiant de fichier)
			load(Date.now().toString());
				}
			}, 100);

			// Fonction abort
			return {
				abort: () => {
				clearInterval(timer);
				abort();
			}
		};
	}
},
// 🔥 Désactiver toutes les previews
allowFilePoster: false,
allowImagePreview: false
});

document.addEventListener('DOMContentLoaded', function () {
const typeSelect = document.getElementById('employeeType');
const typeSalaire = document.getElementById('typeSalaire');
const usernameField = document.getElementById('usernameField');
const valeurSalaire = document.getElementById('valeurSalaire');

function updateFormConstraints() {
	const selectedType = typeSelect.value;

	// Règle 1 : Si ce n'est pas un médecin → Salaire fixe obligatoire
	if (selectedType !== 'Medecin') {
		typeSalaire.value = 'fixe';
		typeSalaire.disabled = true;
	} else {
		typeSalaire.disabled = false;
	}

// Règle 2 : Username visible uniquement pour les types autorisés

}

// Réagir au changement de type
typeSelect.addEventListener('change', updateFormConstraints);

// Initialiser dès le chargement
updateFormConstraints();
});