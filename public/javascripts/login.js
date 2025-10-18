import { ContinuumAuth } from './modules/continuumAuth.js';

const connectBtn = document.getElementById('connectBtn');

const auth = new ContinuumAuth({
    onStatus: (msg) => console.log(msg),
});

connectBtn.addEventListener('click', async () => {
    connectBtn.disabled = true;
    try {
        await auth.login();
    } catch (err) {
        console.error(err);
        connectBtn.disabled = false;
    }
});

auth.attachProviderListeners({
    onAccountsChanged: (accs) => {
        console.log(accs?.length ? `Account changed: ${accs[0]}` : 'No account connected.');
    },
    onChainChanged: () => window.location.reload()
});