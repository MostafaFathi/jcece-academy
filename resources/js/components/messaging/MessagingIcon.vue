<script setup>
defineProps({ name: { type: String, required: true } });

const paths = {
    messages: 'M20 11.5a8.5 8.5 0 0 1-8.5 8.5 9 9 0 0 1-4-.9L3 21l1.9-5.5A8.5 8.5 0 1 1 20 11.5Z M8 10h8 M8 14h5',
    search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z M17 17l4 4',
    user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M4 21v-2a8 8 0 0 1 16 0v2',
    users: 'M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M22 20v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
    book: 'M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21Z M4 5.5V21 M8 8h8 M8 12h7',
    plus: 'M12 5v14 M5 12h14',
    paperclip: 'm21 11.5-8.8 8.8a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5',
    microphone: 'M12 15a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v7a3 3 0 0 0 3 3Z M5 11a7 7 0 0 0 14 0 M12 18v3 M8 21h8',
    stop: 'M6 6h12v12H6z',
    send: 'm22 2-7 20-4-9-9-4 20-7Z M22 2 11 13',
    reply: 'm9 10-5 4 5 4 M4 14h10a6 6 0 0 0 0-12',
    smile: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z M8 10h.01 M16 10h.01 M8 14a5 5 0 0 0 8 0',
    reaction: 'M11 20a8.5 8.5 0 1 1 8.5-8.5 M8 10h.01 M14 10h.01 M8 14a5 5 0 0 0 6 0 M19 16v6 M16 19h6',
    download: 'M12 3v12 m-4-4 4 4 4-4 M4 17v4h16v-4',
    trash: 'M4 7h16 M10 11v6 M14 11v6 M6 7l1 14h10l1-14 M9 7V4h6v3',
    close: 'M5 5l14 14 M19 5 5 19',
    chevron: 'm9 18 6-6-6-6',
    check: 'm4 12 5 5L20 6',
    wifi: 'M3 9a14 14 0 0 1 18 0 M6 12a10 10 0 0 1 12 0 M9 15a5 5 0 0 1 6 0 M12 19h.01',
};
</script>

<template>
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path :d="paths[name] ?? paths.messages" />
    </svg>
</template>
