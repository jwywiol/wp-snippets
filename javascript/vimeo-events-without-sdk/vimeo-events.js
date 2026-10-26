/**
 * Listen to a Vimeo embed's playback events without loading the Vimeo SDK.
 *
 * The player answers subscriptions to the documented event names with the
 * legacy ones: "timeupdate" arrives as "playProgress", "ended" as "finish".
 * This normalises both, and computes progress from seconds/duration because
 * the `percent` field is coarsely rounded.
 *
 *   const stop = watchVimeo( document.querySelector( 'iframe' ), {
 *       onProgress: ( progress, seconds, duration ) => {},
 *       onEnded: () => {},
 *   } );
 *
 * @param {HTMLIFrameElement} iframe   A player.vimeo.com embed.
 * @param {Object}            handlers onReady, onPlay, onPause, onProgress, onEnded.
 * @return {Function} Removes the listener.
 */
export function watchVimeo( iframe, handlers = {} ) {
	const ORIGIN = 'https://player.vimeo.com';
	const LEGACY = { playProgress: 'timeupdate', finish: 'ended' };

	const post = ( method, value ) => {
		const message = value === undefined ? { method } : { method, value };
		iframe.contentWindow?.postMessage( JSON.stringify( message ), ORIGIN );
	};

	const subscribe = () => {
		[ 'play', 'pause', 'timeupdate', 'ended' ].forEach( ( name ) => post( 'addEventListener', name ) );
	};

	const onMessage = ( event ) => {
		// Only this player, only from Vimeo.
		if ( event.origin !== ORIGIN || event.source !== iframe.contentWindow ) {
			return;
		}

		let data = event.data;
		if ( typeof data === 'string' ) {
			try {
				data = JSON.parse( data );
			} catch {
				return;
			}
		}

		const name = LEGACY[ data?.event ] ?? data?.event;
		const info = data?.data ?? {};

		switch ( name ) {
			case 'ready':
				// A subscription sent before "ready" can be lost, so subscribe (again) here.
				subscribe();
				handlers.onReady?.();
				break;
			case 'play':
				handlers.onPlay?.();
				break;
			case 'pause':
				handlers.onPause?.();
				break;
			case 'timeupdate': {
				const progress = info.duration ? info.seconds / info.duration : info.percent ?? 0;
				handlers.onProgress?.( progress, info.seconds ?? 0, info.duration ?? 0 );
				break;
			}
			case 'ended':
				handlers.onProgress?.( 1, info.duration ?? 0, info.duration ?? 0 );
				handlers.onEnded?.();
				break;
		}
	};

	window.addEventListener( 'message', onMessage );
	// If the player was ready before this ran, subscribing again is harmless.
	subscribe();

	return () => window.removeEventListener( 'message', onMessage );
}

/**
 * Commands use the same channel: play, pause, setCurrentTime (seconds),
 * setVolume (0-1), setMuted (boolean).
 *
 * @param {HTMLIFrameElement} iframe Player.
 * @param {string}            method Command.
 * @param {*}                 value  Optional argument.
 */
export function vimeoCommand( iframe, method, value ) {
	const message = value === undefined ? { method } : { method, value };
	iframe.contentWindow?.postMessage( JSON.stringify( message ), 'https://player.vimeo.com' );
}
