// ReservationState Durable Object for Noteria
// Ky është një shembull minimal, mund ta zgjerohet sipas nevojës

export class ReservationState {
	constructor(state, env) {
		this.state = state;
		this.env = env;
	}

	// Shembull: ruaj dhe lexo një vlerë të thjeshtë
	async fetch(request) {
		const url = new URL(request.url);
		if (url.pathname.endsWith('/get')) {
			const value = await this.state.storage.get('value');
			return new Response(JSON.stringify({ value }), {
				headers: { 'Content-Type': 'application/json' }
			});
		}
		if (url.pathname.endsWith('/set')) {
			const { value } = await request.json();
			await this.state.storage.put('value', value);
			return new Response(JSON.stringify({ success: true }), {
				headers: { 'Content-Type': 'application/json' }
			});
		}
		return new Response('Not found', { status: 404 });
	}
}
