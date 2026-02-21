$(document).ready(function () {
    let pondInstance;
    let employeeId;

    // Récupérer l'ID de l'employé depuis l'URL
    const urlParams = new URLSearchParams(window.location.search);
    employeeId = urlParams.get('id') || window.location.pathname.split('/').pop();

    // Fetch les données de l'employé
    fetch(`/api/employee/${employeeId}`)
        .then(response => response.json())
        .then(data => {
            const existingFiles = data.administrativeFiles;

            FilePond.registerPlugin(FilePondPluginFileValidateType, FilePondPluginFileValidateSize);

            pondInstance = FilePond.create(document.querySelector('#administrativeFiles'), {
                allowMultiple: true,
                storeAsFile: true,
                maxFileSize: '5MB',
                acceptedFileTypes: [
                    'application/pdf', 'image/*'
                ],
                labelIdle: `Glissez‑déposez vos fichiers ou <span class="filepond--label-action">Parcourir</span>`,
                server: {
                    process: (fieldName, file, metadata, load, error, progress, abort) => {
                        const total = file.size;
                        let loaded = 0;
                        const speed = total / 20;
                        const timer = setInterval(() => {
                            loaded = Math.min(loaded + speed, total);
                            progress(true, loaded, total);
                            if (loaded >= total) {
                                clearInterval(timer);
                                load(Date.now().toString());
                            }
                        }, 100);
                        return {
                            abort: () => {
                                clearInterval(timer);
                                abort();
                            }
                        };
                    }
                },
                allowFilePoster: true,
                allowImagePreview: true
            });

            existingFiles.forEach(fileUrl => {
                const fileName = fileUrl.split('/').pop();
                const ext = fileName.split('.').pop().toLowerCase();
                let fileType = 'application/octet-stream';
                if (ext === 'pdf')
                    fileType = 'application/pdf';
                else if (['jpg', 'jpeg', 'png', 'gif'].includes(ext))
                    fileType = 'image/' + ext;

                pondInstance.addFile(window.location.origin + fileUrl, {
                    type: 'local',
                    file: {
                        name: fileName,
                        type: fileType
                    }
                }).catch(err => {
                    console.error("❌ Erreur affichage fichier :", fileUrl, err);
                    showToastModal({message: "Impossible d'afficher un fichier existant", type: "warning"});
                });
            });
        })
        .catch(error => {
            console.error('Erreur lors de la récupération des données employé:', error);
        });

    // ===== FORMULAIRE DE MISE À JOUR =====
    $('#employeeDetailsForm').on('submit', function (e) {
        e.preventDefault();
        $('#typeHidden').val($('#typeSelect').val());

        const formData = new FormData();
        $(this).serializeArray().forEach(({name, value}) => {
            if (!name.startsWith('comingDays'))
                formData.append(name, value);
        });

        $('input[name="comingDays[]"]:checked').each(function () {
            formData.append('comingDays[]', $(this).val());
        });

        if (pondInstance) {
            pondInstance.getFiles().forEach(fileItem => {
                formData.append('administrativeFiles[]', fileItem.file);
            });
        }

        $.ajax({
            url: `/api/employee/update/${employeeId}`,
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                showToastModal({
                    message: response.message || 'Employé mis à jour avec succès',
                    type: 'success'
                });
            },
            error: function (xhr) {
                const message = xhr.responseText || 'Erreur inconnue';
                showToastModal({
                    message: 'Erreur : ' + message,
                    type: 'error',
                    duration: 3000
                });
            }
        });
    });
});