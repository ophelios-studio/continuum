export async function getAuthSig({ chain = 'sepolia', statement = 'Authenticate with Lit Protocol', expirationMinutes = 30 } = {}) {
    const eth = window.ethereum?.providers?.[0] || window.ethereum;
    if (!eth) throw new Error('Wallet provider not found');
    const [address] = await eth.request({ method: 'eth_requestAccounts' });
    if (!address) throw new Error('No account');

    const expiration = new Date(Date.now() + expirationMinutes * 60 * 1000).toISOString();
    const msg = [
        statement,
        `Address: ${address}`,
        `Chain: ${chain}`,
        `Expiration: ${expiration}`
    ].join('\n');

    // EIP-191 personal_sign
    const sig = await eth.request({
        method: 'personal_sign',
        params: [msg, address]
    });
    return {
        sig,
        derivedVia: 'web3.personalSign',
        signedMessage: msg,
        address
    };
}