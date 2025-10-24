// getAuthSigSiwe.js
import { getAddress } from 'https://esm.sh/viem@2.14.1';

export async function getAuthSigSiwe({
                                         chain = 'sepolia',                        // keep 'sepolia' for your setup
                                         statement = 'Authenticate with Lit Protocol',
                                         expirationMinutes = 30
                                     } = {}) {
    const eth = window.ethereum?.providers?.[0] || window.ethereum;
    if (!eth) throw new Error('Wallet provider not found');

    const [addrRaw] = await eth.request({ method: 'eth_requestAccounts' });
    if (!addrRaw) throw new Error('No account');

    // EIP-55 checksum is REQUIRED by Lit nodes
    const address = getAddress(addrRaw);

    // SIWE required fields
    const domain = window.location.host;            // e.g. "localhost:443" or "localhost"
    const origin = window.location.origin;          // e.g. "https://localhost"
    const chainId = chain === 'sepolia' ? 11155111  : 1; // adjust if you change chains
    const issuedAt = new Date().toISOString();
    const expiration = new Date(Date.now() + expirationMinutes * 60_000).toISOString();
    const nonce = hexNonce(16); // 16 random bytes → 32 hex chars

    // Strict SIWE v1 formatting (no extra spaces, correct order)
    const siweMessage =
        `${domain} wants you to sign in with your Ethereum account:
${address}

${statement}

URI: ${origin}
Version: 1
Chain ID: ${chainId}
Nonce: ${nonce}
Issued At: ${issuedAt}
Expiration Time: ${expiration}`;

    // EIP-191 personal_sign
    const sig = await eth.request({
        method: 'personal_sign',
        params: [siweMessage, address]
    });

    return {
        sig,
        derivedVia: 'web3.personalSign',
        signedMessage: siweMessage,
        address
    };
}

function hexNonce(bytes = 16) {
    const buf = new Uint8Array(bytes);
    crypto.getRandomValues(buf);
    return [...buf].map(b => b.toString(16).padStart(2, '0')).join('');
}