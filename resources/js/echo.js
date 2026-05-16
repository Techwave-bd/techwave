// import Echo from "laravel-echo";

// import Pusher from "pusher-js";
// window.Pusher = Pusher;

// window.Echo = new Echo({
//     broadcaster: "reverb",
//     key: import.meta.env.VITE_REVERB_APP_KEY,
//     wsHost: import.meta.env.VITE_REVERB_HOST,
//     wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
//     wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
//     forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
//     enabledTransports: ['ws', 'wss'],
// });


import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

// Temporary debug log
Pusher.logToConsole = true;

window.Echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,

    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),

    forceTLS: true,
    encrypted: true,

    // Revert this
    enabledTransports: ["ws", "wss"],

    // Use this instead of disableStats
    enableStats: false,
});

window.Echo.connector.pusher.connection.bind("connected", () => {
    console.log("✅ Reverb connected");
});

window.Echo.connector.pusher.connection.bind("error", (error) => {
    console.log("❌ Reverb error", error);
});

window.Echo.connector.pusher.connection.bind("state_change", (states) => {
    console.log("🔄 Reverb state changed", states);
});