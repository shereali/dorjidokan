import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  scenarios: {
    authenticated_reads: {
      executor: 'ramping-vus',
      stages: [
        { duration: '30s', target: 20 },
        { duration: '2m', target: 20 },
        { duration: '30s', target: 0 },
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<500'],
  },
};

const baseUrl = __ENV.API_BASE_URL;
const token = __ENV.API_TOKEN;
const tenant = __ENV.TENANT_SLUG;

export function setup() {
  if (!baseUrl || !token || !tenant) {
    throw new Error('API_BASE_URL, API_TOKEN, and TENANT_SLUG are required. Use a read-only load-test service token.');
  }
}

export default function () {
  const headers = { Authorization: `Bearer ${token}`, 'X-Tenant': tenant, Accept: 'application/json' };
  for (const path of ['/api/v1/dashboard', '/api/v1/customers', '/api/v1/orders', '/api/v1/inventory']) {
    const response = http.get(`${baseUrl}${path}`, { headers, tags: { endpoint: path } });
    check(response, { [`${path} returns 200`]: result => result.status === 200 });
  }
  sleep(1);
}
