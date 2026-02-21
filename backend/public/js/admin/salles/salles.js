$(document).ready(function () {
$('#sallesTable').DataTable();

// Open Add Modal
document.getElementById('openAddModal').addEventListener('click', () => {
const addModal = new bootstrap.Modal(document.getElementById('addModal'));
addModal.show();
});

// Open Edit Modal
const editButtons = document.querySelectorAll('.edit-btn');
editButtons.forEach(button => {
button.addEventListener('click', () => {
const id = button.getAttribute('data-id');
const nom = button.getAttribute('data-nom');
const description = button.getAttribute('data-description');
document.getElementById('edit-id').value = id;
document.getElementById('edit-nom').value = nom;
document.getElementById('edit-description').value = description;

const editModal = new bootstrap.Modal(document.getElementById('editModal'));
editModal.show();
});
});

// Open Delete Modal
const deleteButtons = document.querySelectorAll('form[action*="app_admin_salle_delete"] button');
deleteButtons.forEach(button => {
button.addEventListener('click', (e) => {
e.preventDefault();
const form = button.closest('form');
const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
const deleteForm = document.getElementById('deleteForm');
deleteForm.action = form.action;
deleteModal.show();
});
});

$('.btn-close').on('click', function () {
$(this).closest('.modal').modal('hide');
});
});