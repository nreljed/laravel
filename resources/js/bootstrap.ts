import axios from "axios";

function setupAxios(): void {
  axios.defaults.withCredentials = true;
  axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
}

setupAxios();
