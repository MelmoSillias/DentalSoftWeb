$(document).ready(function () {
    $('#usersTable').DataTable({
        language: {
            sEmptyTable: "Aucune donnée disponible",
            sInfo: "Affichage de _START_ à _END_ sur _TOTAL_ éléments",
            sLengthMenu: "Afficher _MENU_ éléments",
            sSearch: "Rechercher :",
            oPaginate: {
                sNext: "Suivant",
                sPrevious: "Précédent"
            }
        }
    });

    // --- Gestion des modals ---

    // Ouverture modal "Ajouter un utilisateur"
    $('#btnAddUser').on('click', function () {
        $('#addUserModal').modal('show');
    });

    // Soumission du formulaire d'ajout d'utilisateur
    $('#addUserForm').on('submit', function (e) {
        e.preventDefault();
        let payload = {
            username: $('#newUsername').val(),
            employee_id: $('#newEmployee').val()
        };
        fetch('/api/users', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        }).then(response => response.json()).then(function (data) {
            if (data.success) {
                $('#addUserModal').modal('hide');
                showToastModal({message: 'Utilisateur créé avec succès', type: 'success'});
                setTimeout(() => location.reload(), 800);
            } else {
                showToastModal({message: "Erreur lors de la création de l'utilisateur", type: 'error', duration: 3000});
            }
        }).catch(function (error) {
            console.error(error);
            showToastModal({message: "Erreur lors de la création de l'utilisateur", type: 'error', duration: 3000});
        });
    });

    // Ouverture modal pour modification du username
    $('.btn-edit-username').on('click', function () {
        let userId = $(this).data('user-id');
        let $row = $(this).closest('tr');
        let currentUsername = $row.find('.username-cell').text().trim();
        $('#editUserId').val(userId);
        $('#editUsername').val(currentUsername);
        $('#oldUsername').text(currentUsername);
        $('#editUsernameModal').modal('show');
    });

    // Soumission du formulaire de modification du username
    $('#editUsernameForm').on('submit', function (e) {
        e.preventDefault();
        let payload = {
            user_id: $('#editUserId').val(),
            username: $('#editUsername').val()
        };
        fetch(`/api/users/${payload.user_id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        }).then(response => response.json()).then(function (data) {
            if (data.success) {
                $('#editUsernameModal').modal('hide');
                showToastModal({message: "Nom d'utilisateur mis à jour", type: 'success'});
                setTimeout(() => location.reload(), 800);
            } else {
                showToastModal({message: "Erreur lors de la modification du nom d'utilisateur", type: 'error', duration: 3000});
            }
        }).catch(function (error) {
            console.error(error);
            showToastModal({message: "Erreur lors de la modification du nom d'utilisateur", type: 'error', duration: 3000});
        });
    });

    // Ouverture modal pour réinitialiser le mot de passe
    $('.btn-reset-pwd').on('click', function () {
        let userId = $(this).data('user-id');
        $('#resetUserId').val(userId);
        $('#resetPwdModal').modal('show');
    });

    // Soumission du formulaire de réinitialisation du mot de passe
    $('#resetPwdForm').on('submit', function (e) {
        e.preventDefault();
        let payload = {
            user_id: $('#resetUserId').val(),
            password: $('#newPassword').val()
        };
        fetch(`/api/users/${payload.user_id}/reset-password`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        }).then(response => response.json()).then(function (data) {
            if (data.success) {
                $('#resetPwdModal').modal('hide');
                showToastModal({message: "Mot de passe réinitialisé avec succès", type: 'success'});
            } else {
                showToastModal({message: "Erreur lors de la réinitialisation du mot de passe", type: 'error', duration: 3000});
            }
        }).catch(function (error) {
            console.error(error);
            showToastModal({message: "Erreur lors de la réinitialisation du mot de passe", type: 'error', duration: 3000});
        });
    });

    // Ouverture modal pour suppression d'un utilisateur
    $('.btn-delete-user').on('click', function () {
        let userId = $(this).data('user-id');
        $('#deleteUserModal').data('user-id', userId).modal('show');
    });

    // Confirmation de suppression
    $('#confirmDeleteBtn').on('click', function () {
        let userId = $('#deleteUserModal').data('user-id');
        fetch(`/api/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({user_id: userId})
        }).then(response => response.json()).then(function (data) {
            if (data.success) {
                $('#deleteUserModal').modal('hide');
                showToastModal({message: "Utilisateur supprimé", type: 'success'});
                setTimeout(() => location.reload(), 800);
            } else {
                showToastModal({message: "Erreur lors de la suppression de l'utilisateur", type: 'error', duration: 3000});
            }
        }).catch(function (error) {
            console.error(error);
            showToastModal({message: "Erreur lors de la suppression de l'utilisateur", type: 'error', duration: 3000});
        });
    });

    // Fermeture des modals
    $('.btn-closer').on('click', function () {
        $(this).closest('.modal').modal('hide');
    });
});