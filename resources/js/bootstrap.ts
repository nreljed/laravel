import axios from "axios";

axios.defaults.withCredentials = true;
axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

let activeRequests = 0;

axios.interceptors.request.use((config) => {
  activeRequests++;
  return config;
});

axios.interceptors.response.use(
  (response) => {
    activeRequests--;
    return response;
  },
  (error) => {
    activeRequests--;
    return Promise.reject(error);
  },
);
