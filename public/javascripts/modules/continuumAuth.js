export class ContinuumAuth {

    static buildSiweMessage({ domain, address, statement, uri, version, chainId, nonce, issuedAt }) {
        return [
            `${domain} wants you to sign in with your Ethereum account:`,
            `${address}`,
            ``,
            `${statement}`,
            ``,
            `URI: ${uri}`,
            `Version: ${version}`,
            `Chain ID: ${chainId}`,
            `Nonce: ${nonce}`,
            `Issued At: ${issuedAt}`
        ].join('\n');
    }

    /**
     * @param {Object} opts
     * @param {string} [opts.noncePath='/auth/siwe/nonce']
     * @param {string} [opts.verifyPath='/auth/siwe/verify']
     * @param {string} [opts.statement='Authenticate to Continuum.']
     * @param {string} [opts.redirectTo='/app']
     * @param {(msg:string)=>void} [opts.onStatus] - optional status sink
     */
    constructor(opts = {}) {
        this.noncePath = opts.noncePath  ?? '/auth/siwe/nonce';
        this.verifyPath = opts.verifyPath ?? '/auth/siwe/verify';
        this.statement = opts.statement  ?? 'Authenticate to Continuum.';
        this.redirectTo = opts.redirectTo ?? '/';
        this.onStatus = typeof opts.onStatus === 'function' ? opts.onStatus : () => {};
    }

    status(msg) {
        this.onStatus(msg);
    }

    get provider() {
        return (typeof window !== 'undefined') ? window.ethereum : undefined;
    }

    ensureProvider() {
        if (!this.provider) {
            throw new Error('No wallet found. Install MetaMask or a compatible wallet.');
        }
    }

    async getChainId() {
        this.ensureProvider();
        const hex = await this.provider.request({ method: 'eth_chainId' });
        return parseInt(hex, 16);
    }

    async connectWallet() {
        this.ensureProvider();
        const [address] = await this.provider.request({ method: 'eth_requestAccounts' });
        return address;
    }

    async fetchNonce() {
        const res = await fetch(this.noncePath, { credentials: 'include' });
        if (!res.ok) {
            throw new Error('Failed to get nonce');
        }
        const { nonce } = await res.json();
        if (!nonce) {
            throw new Error('Nonce missing');
        }
        return nonce;
    }

    async sign(message, address) {
        return await this.provider.request({
            method: 'personal_sign',
            params: [message, address]
        });
    }

    async verify(message, signature) {
        const res = await fetch(this.verifyPath, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message, signature })
        });
        if (!res.ok) {
            const text = await res.text();
            throw new Error(text || 'Verification failed');
        }
        return res.json();
    }

    async login({ redirect = true } = {}) {
        // Connect wallet
        const address = await this.connectWallet();
        this.status(`Wallet: ${address.slice(0,6)}...${address.slice(-4)}, fetching nonce ...`);
        const nonce = await this.fetchNonce();

        // Build SIWE message
        const domain= window.location.host;
        const origin= window.location.origin;
        const chainId= await this.getChainId();
        const issuedAt= new Date().toISOString();
        const message = ContinuumAuth.buildSiweMessage({
            domain,
            address,
            statement: this.statement,
            uri: origin,
            version: '1',
            chainId,
            nonce,
            issuedAt
        });

        // Sign
        const signature = await this.sign(message, address);
        this.status('Verifying signature ...');

        // Verification and session
        const out = await this.verify(message, signature);
        this.status(`Signed in as ${out.address}`);

        if (redirect) {
            window.location.href = this.redirectTo;
        }
        return out;
    }

    attachProviderListeners({ onAccountsChanged, onChainChanged } = {}) {
        if (!this.provider) {
            return;
        }
        if (onAccountsChanged) {
            this.provider.on?.('accountsChanged', onAccountsChanged);
        }
        if (onChainChanged) {
            this.provider.on?.('chainChanged', onChainChanged);
        }
    }
}