import { Router } from 'itty-router';

const router = Router();

// ==================== MIDDLEWARE ====================

const logRequest = (request) => {
    const timestamp = new Date().toISOString();
    console.log(`[${timestamp}] ${request.method} ${new URL(request.url).pathname}`);
};

const rateLimitCheck = async (ip, limit = 100, window = 60) => {
    // TODO: Implement with Durable Objects
    return true;
};

class CacheManager {
    constructor(kv_namespace) {
        this.kv = kv_namespace;
    }

    async get(key) {
        try {
            const value = await this.kv.get(key);
            return value ? JSON.parse(value) : null;
        } catch (error) {
            console.error(`Cache get error: ${error.message}`);
            return null;
        }
    }

    async set(key, value, ttl = 3600) {
        try {
            await this.kv.put(key, JSON.stringify(value), {
                expirationTtl: ttl
            });
        } catch (error) {
            console.error(`Cache set error: ${error.message}`);
        }
    }

    async delete(key) {
        try {
            await this.kv.delete(key);
        } catch (error) {
            console.error(`Cache delete error: ${error.message}`);
        }
    }
}

// ==================== NEWS ENDPOINTS ====================

router.get('/api/news', async (request, env) => {
    logRequest(request);
    const cache = new CacheManager(env.CACHE);
    const cacheKey = 'news:all';
    const ip = request.headers.get('cf-connecting-ip');

    try {
        const rateLimited = !(await rateLimitCheck(ip));
        if (rateLimited) {
            return new Response(JSON.stringify({ error: 'Rate limit exceeded' }), {
                status: 429,
                headers: { 'Content-Type': 'application/json' }
            });
        }

        const cached = await cache.get(cacheKey);
        if (cached) {
            return new Response(JSON.stringify(cached), {
                status: 200,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Cache': 'HIT',
                    'Cache-Control': 'public, max-age=86400',
                    'Access-Control-Allow-Origin': '*'
                }
            });
        }

        const backendUrl = `${env.BACKEND_URL}/api/news`;
        const response = await fetch(backendUrl, {
            method: 'GET',
            headers: {
                'X-Forwarded-For': ip,
                'X-CloudFlare-Worker': 'true'
            }
        });

        if (!response.ok) {
            throw new Error(`Backend error: ${response.status}`);
        }

        const news = await response.json();
        await cache.set(cacheKey, news, 86400);

        return new Response(JSON.stringify(news), {
            status: 200,
            headers: {
                'Content-Type': 'application/json',
                'X-Cache': 'MISS',
                'Cache-Control': 'public, max-age=86400',
                'Access-Control-Allow-Origin': '*'
            }
        });
    } catch (error) {
        return new Response(JSON.stringify({
            error: error.message,
            timestamp: new Date().toISOString()
        }), {
            status: 500,
            headers: {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            }
        });
    }
});

router.get('/api/news/:id', async (request, env) => {
    logRequest(request);
    const { id } = request.params;
    const cache = new CacheManager(env.CACHE);
    const cacheKey = `news:${id}`;
    const ip = request.headers.get('cf-connecting-ip');

    try {
        const cached = await cache.get(cacheKey);
        if (cached) {
            return new Response(JSON.stringify(cached), {
                status: 200,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Cache': 'HIT',
                    'Cache-Control': 'public, max-age=86400',
                    'Access-Control-Allow-Origin': '*'
                }
            });
        }

        const response = await fetch(`${env.BACKEND_URL}/api/news/${id}`, {
            headers: { 'X-Forwarded-For': ip }
        });

        if (!response.ok) {
            return new Response(JSON.stringify({ error: 'Not found' }), {
                status: 404,
                headers: { 'Content-Type': 'application/json' }
            });
        }

        const news = await response.json();
        await cache.set(cacheKey, news, 86400);

        return new Response(JSON.stringify(news), {
            status: 200,
            headers: {
                'Content-Type': 'application/json',
                'X-Cache': 'MISS',
                'Access-Control-Allow-Origin': '*'
            }
        });
    } catch (error) {
        return new Response(JSON.stringify({ error: error.message }), {
            status: 500,
            headers: { 'Content-Type': 'application/json' }
        });
    }
});

// ==================== RESERVATIONS ====================

router.post('/api/reservations', async (request, env) => {
    logRequest(request);
    const ip = request.headers.get('cf-connecting-ip');

    try {
        const data = await request.json();

        if (!data.user_id || !data.zyra_id || !data.date) {
            return new Response(JSON.stringify({
                error: 'Missing required fields: user_id, zyra_id, date'
            }), {
                status: 400,
                headers: { 'Content-Type': 'application/json' }
            });
        }

        const response = await fetch(`${env.BACKEND_URL}/api/reservations`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Forwarded-For': ip
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        const cache = new CacheManager(env.CACHE);
        await cache.delete(`reservation:${result.id}`);
        await cache.delete(`reservation:user:${data.user_id}`);

        return new Response(JSON.stringify(result), {
            status: response.status,
            headers: {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            }
        });
    } catch (error) {
        return new Response(JSON.stringify({ error: error.message }), {
            status: 500,
            headers: { 'Content-Type': 'application/json' }
        });
    }
});

router.get('/api/reservations/:id', async (request, env) => {
    logRequest(request);
    const { id } = request.params;
    const cache = new CacheManager(env.CACHE);
    const cacheKey = `reservation:${id}`;

    try {
        const cached = await cache.get(cacheKey);
        if (cached) {
            return new Response(JSON.stringify(cached), {
                status: 200,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Cache': 'HIT',
                    'Cache-Control': 'private, max-age=300',
                    'Access-Control-Allow-Origin': '*'
                }
            });
        }

        const response = await fetch(`${env.BACKEND_URL}/api/reservations/${id}`, {
            headers: {
                'X-Forwarded-For': request.headers.get('cf-connecting-ip')
            }
        });

        if (!response.ok) {
            return new Response(JSON.stringify({ error: 'Not found' }), {
                status: 404,
                headers: { 'Content-Type': 'application/json' }
            });
        }

        const reservation = await response.json();
        await cache.set(cacheKey, reservation, 300);

        return new Response(JSON.stringify(reservation), {
            status: 200,
            headers: {
                'Content-Type': 'application/json',
                'X-Cache': 'MISS',
                'Access-Control-Allow-Origin': '*'
            }
        });
    } catch (error) {
        return new Response(JSON.stringify({ error: error.message }), {
            status: 500,
            headers: { 'Content-Type': 'application/json' }
        });
    }
});

// ==================== USERS ====================

router.get('/api/users/:id', async (request, env) => {
    logRequest(request);
    const { id } = request.params;
    const cache = new CacheManager(env.CACHE);
    const cacheKey = `user:${id}`;

    try {
        const cached = await cache.get(cacheKey);
        if (cached) {
            return new Response(JSON.stringify(cached), {
                status: 200,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Cache': 'HIT',
                    'Cache-Control': 'private, max-age=600',
                    'Access-Control-Allow-Origin': '*'
                }
            });
        }

        const response = await fetch(`${env.BACKEND_URL}/api/users/${id}`);

        if (!response.ok) {
            return new Response(JSON.stringify({ error: 'Not found' }), {
                status: 404,
                headers: { 'Content-Type': 'application/json' }
            });
        }

        const user = await response.json();
        await cache.set(cacheKey, user, 600);

        return new Response(JSON.stringify(user), {
            status: 200,
            headers: {
                'Content-Type': 'application/json',
                'X-Cache': 'MISS',
                'Access-Control-Allow-Origin': '*'
            }
        });
    } catch (error) {
        return new Response(JSON.stringify({ error: error.message }), {
            status: 500,
            headers: { 'Content-Type': 'application/json' }
        });
    }
});

// ==================== HEALTH CHECK ====================

router.get('/health', () => {
    return new Response(JSON.stringify({
        status: 'ok',
        timestamp: new Date().toISOString(),
        version: '1.0.0',
        environment: 'cloudflare-workers'
    }), {
        status: 200,
        headers: {
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache'
        }
    });
});

router.get('/status', async (request, env) => {
    logRequest(request);

    try {
        const backendCheck = await fetch(`${env.BACKEND_URL}/health`);
        const backendOk = backendCheck.ok;

        return new Response(JSON.stringify({
            status: 'ok',
            timestamp: new Date().toISOString(),
            worker: {
                status: 'online',
                region: request.cf?.colo,
                colo: request.cf?.colo
            },
            backend: {
                status: backendOk ? 'online' : 'offline',
                responseTime: backendCheck.ok ? '✓' : '✗'
            },
            cache: {
                status: 'online'
            }
        }), {
            status: 200,
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache'
            }
        });
    } catch (error) {
        return new Response(JSON.stringify({
            status: 'error',
            error: error.message,
            timestamp: new Date().toISOString()
        }), {
            status: 500,
            headers: { 'Content-Type': 'application/json' }
        });
    }
});

// ==================== OPTIONS (CORS) ====================

router.options('*', () => {
    return new Response(null, {
        status: 204,
        headers: {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization',
            'Access-Control-Max-Age': '86400'
        }
    });
});

// ==================== 404 HANDLER ====================

router.all('*', () => {
    return new Response(JSON.stringify({
        error: 'Not Found',
        path: '404'
    }), {
        status: 404,
        headers: { 'Content-Type': 'application/json' }
    });
});

// ==================== EXPORT ====================

export default {
    fetch: (request, env, ctx) => router.fetch(request, env, ctx)
};