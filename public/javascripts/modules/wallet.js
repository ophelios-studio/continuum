import { getAddress } from 'https://esm.sh/viem@2.14.1';

export async function ensureChain(provider, targetChainId) {
    const current = await provider.request({ method: "eth_chainId" });
    const currentDec = parseInt(current, 16);
    if (currentDec === targetChainId) return;
    try {
        console.log("Trying to switch network");
        await provider.request({
            method: "wallet_switchEthereumChain",
            params: [{ chainId: "0x" + targetChainId.toString(16) }],
        });
    } catch (err) {
        console.log("Error switching network:", err);
        if (err?.code === 4902) {
            throw new Error("Please add Sepolia (chainId 11155111) to your wallet and retry.");
        }
        throw new Error("Network switch was rejected. Please switch to Sepolia.");
    }
}

export function getProvider() {
    const provider = window.ethereum;
    if (!provider) throw new Error("No wallet detected. Install MetaMask or a compatible wallet.");
    return provider;
}

export async function getAccount(provider) {
    const [account] = await provider.request({ method: "eth_requestAccounts" });
    if (!account) throw new Error("No account connected.");
    return account;
}

function hexToBigInt(hex) { return BigInt(hex); }
function toHex(bi) { return "0x" + bi.toString(16); }

export async function estimateGas(provider, account, contractAddress, data) {
    const latestBlock = await provider.request({
        method: "eth_getBlockByNumber",
        params: ["latest", false],
    });
    const cap = latestBlock?.gasLimit ? hexToBigInt(latestBlock.gasLimit) : BigInt(16_000_000);

    // estimate for this call
    let est;
    try {
        const estHex = await provider.request({
            method: "eth_estimateGas",
            params: [{ from: account, to: contractAddress, data }],
        });
        est = hexToBigInt(estHex);
    } catch (e) {
        est = BigInt(300_000);
    }

    const safety = (est * BigInt(125)) / BigInt(100); // +25%
    const buffer = BigInt(50_000);
    const maxAllowed = cap > buffer ? (cap - buffer) : cap;
    let gasFinal = safety;
    if (gasFinal > maxAllowed) gasFinal = maxAllowed;
    if (gasFinal < BigInt(150_000)) gasFinal = BigInt(150_000); // minimum sensible gas for this function
    return toHex(gasFinal);
}

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