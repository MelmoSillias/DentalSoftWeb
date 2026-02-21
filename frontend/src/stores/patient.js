import { defineStore } from 'pinia';
import { ref } from 'vue';
import http from '@/service/http';

const patients = ref([]);

export const usePatientsStore = defineStore('patients', () => {
    const fetchPatients = async () => {
        try {
            const response = await http.get('https://api.example.com/patients');
            const data = response.data;
            patients.value = data;
        } catch (error) {
            console.error('Error fetching patients:', error);
        }
    };

    const fetchPatientById = async (id) => {
        try {
            const response = await http.get(`https://api.example.com/patients/${id}`);
            const data = response.data;
            patients.value = data;
        } catch (error) {
            console.error('Error fetching patients by ID:', error);
        }
    };

    const addPatient = (patient) => {
        patients.value.push(patient);
    };

    function postPatient(url = '', data = {}) {
        return http
            .post(url, data, {
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then((response) => response.data);
    }

    return { patients, fetchPatients, fetchPatientById, addPatient, postPatient };
});
