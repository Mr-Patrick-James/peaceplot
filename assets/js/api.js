const API_BASE_URL = '/peaceplot/api';

const API = {
    async fetchLots() {
        try {
            const response = await fetch(`${API_BASE_URL}/cemetery_lots.php`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching lots:', error);
            return { success: false, message: error.message };
        }
    },

    async fetchLot(id) {
        try {
            const response = await fetch(`${API_BASE_URL}/cemetery_lots.php?id=${id}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching lot:', error);
            return { success: false, message: error.message };
        }
    },

    async createLot(lotData) {
        try {
            const response = await fetch(`${API_BASE_URL}/cemetery_lots.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(lotData)
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error creating lot:', error);
            return { success: false, message: error.message };
        }
    },

    async updateLot(id, lotData) {
        try {
            const response = await fetch(`${API_BASE_URL}/cemetery_lots.php`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ ...lotData, id })
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error updating lot:', error);
            return { success: false, message: error.message };
        }
    },

    async deleteRecord(id, action = 'archive') {
        try {
            const response = await fetch(`${API_BASE_URL}/burial_records.php`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id, action })
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error with record action:', error);
            return { success: false, message: error.message };
        }
    }
};
