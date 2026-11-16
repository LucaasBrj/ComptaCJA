// API_URL permet de cibler l'API depuis un conteneur (ex. http://api:8000).
export default {
  '/api': {
    target: process.env.API_URL ?? 'http://127.0.0.1:8000',
    secure: false,
    changeOrigin: true,
  },
};
