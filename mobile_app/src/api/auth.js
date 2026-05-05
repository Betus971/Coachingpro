import apiClient from './client';

export const authService = {
  login: async (email, password) => {
    const response = await apiClient.post('/api/login_check', {
      email,
      password,
    });
    return response.data;
  },
  
  register: async (userData) => {
    const response = await apiClient.post('/api/users', userData);
    return response.data;
  },

  getProfile: async () => {
    const response = await apiClient.get('/api/me'); // Endpoint à vérifier/créer si besoin
    return response.data;
  }
};
