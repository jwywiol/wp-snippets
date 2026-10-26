# Vimeo embed events without the SDK: the legacy event names

## Problem

You need to react to a Vimeo video on a landing page: show a button after 80% of the video, record progress, unlock the next step at the end. Rather than load the official SDK (`@vimeo/player`) for a few messages, you talk to the iframe directly with `postMessage`. You subscribe to `timeupdate` and `ended`, and nothing ever arrives.

## Solution

Subscribe with the documented names, but also accept the legacy names the player actually sends back: `playProgress` instead of `timeupdate`, and `finish` instead of `ended`. Compute progress from `seconds / duration`.

## Why it works

- When you send `{"method":"addEventListener","value":"timeupdate"}` over raw `postMessage`, `player.vimeo.com` accepts it but emits the progress events as `playProgress`, the event name of its older embed API. The end of playback arrives as `finish`. Handling both names keeps the code working if the player ever switches to the new names.
- A subscription sent before the player has loaded can be lost. Re-subscribing on the `ready` event, and once right away in case `ready` has already fired, makes the timing irrelevant.
- `data.percent` in progress events is rounded coarsely. `seconds / duration` gives an exact value, which matters for thresholds like "80% watched".
- Checking both `event.origin` and `event.source` ignores messages from other frames and from other Vimeo players on the same page.

Tested against `player.vimeo.com` in October 2026 with this file and two public videos. The player sent `ready, play, playProgress, pause, finish`, and the handlers received `onProgress` up to `1.0` and `onEnded`.

## How to use

```html
<iframe id="intro" src="https://player.vimeo.com/video/VIDEO_ID" allow="autoplay; fullscreen" title="Intro video"></iframe>

<script type="module">
	import { watchVimeo } from './vimeo-events.js';

	const unlock = document.querySelector( '#next-step' );

	watchVimeo( document.getElementById( 'intro' ), {
		onProgress: ( progress ) => {
			if ( progress >= 0.8 ) unlock.hidden = false;
		},
		onEnded: () => console.log( 'watched to the end' ),
	} );
</script>
```

`vimeoCommand( iframe, 'setCurrentTime', 30 )` sends commands on the same channel.

## Notes

- Testing: headless browsers often get a bot challenge from Vimeo. Use a headed browser, started with `--autoplay-policy=no-user-gesture-required` if the test needs autoplay.
- The file is an ES module; drop the `export` keywords to use it as a classic script.
