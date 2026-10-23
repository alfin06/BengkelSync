import axios from 'axios';

// Used by JSON endpoints (autocomplete). Laravel's XSRF-TOKEN cookie is sent automatically.
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.axios = axios;
