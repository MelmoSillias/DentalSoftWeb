$(document).ready(() => {
    const $container = $('#consultations-container');
    const url = $container.data('json-url');

    let consultationToCancelId = null;

$(document).on('click', '.btn-cancel-consultation', function () {
    consultationToCancelId = $(this).data('id');
    $('#confirmCancelModal').modal('show');
});

$('#confirmCancelBtn').click(function () {
    if (!consultationToCancelId) return;

    $.ajax({
        url: `/api/consultation/${consultationToCancelId}`,
        type: 'DELETE',
        success: function () {
            $('#confirmCancelModal').modal('hide');
            showToastModal({
                message: 'Consultation annulée avec succès.',
                type: 'success',
                duration: 3000
            });
            $(`#consultation-card-${consultationToCancelId}`).fadeOut(300, function () {
                $(this).remove();
            
                // Optionnel : afficher une alerte si plus rien
                if ($('.consultation-card').length === 0) {
                    $('#consultations-container').html(`
                        <div class="col-12">
                            <div class="alert alert-info">
                                Aucune consultation en attente
                            </div>
                        </div>
                    `);
                }
            });
        },
        error: function () {
            $('#confirmCancelModal').modal('hide');
            showToastModal({
                message: "Erreur lors de l'annulation.",
                type: "error",
                duration: 3000
            });
        }
    });
});

});
