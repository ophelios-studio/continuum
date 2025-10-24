import { LitNodeClient } from 'https://esm.sh/@lit-protocol/lit-node-client@7';
import { LIT_NETWORK } from 'https://esm.sh/@lit-protocol/constants@7';
import { encryptFile } from 'https://esm.sh/@lit-protocol/encryption@7';
import { getAuthSigSiwe } from "./wallet.js"

export async function encrypt({file, registry, evidenceIdHex, uploadUrl, chain = "sepolia", meta = {}}) {
    if (!file) throw new Error('No file provided');
    if (!registry) throw new Error('registry is required');
    if (!evidenceIdHex) throw new Error('evidenceIdHex is required');
    if (!uploadUrl) throw new Error('uploadUrl is required');
    if (!window.ethereum) throw new Error('Wallet provider not found');

    const client = new LitNodeClient({ litNetwork: LIT_NETWORK.Datil });
    await client.connect();
    const authSig = await getAuthSigSiwe({ chain });
    const evmContractConditions = [
        {
            contractAddress: registry,
            chain: chain,
            functionName: 'currentCustodian',
            functionParams: [evidenceIdHex],
            functionAbi: {
                inputs: [{ internalType: 'bytes32', name: 'evidenceId', type: 'bytes32' }],
                name: 'currentCustodian',
                outputs: [{ internalType: 'address', name: '', type: 'address' }],
                stateMutability: 'view',
                type: 'function'
            },
            returnValueTest: {
                "key": "",
                comparator: '=',
                value: ':userAddress'
            }
        }
    ];
    const { ciphertext, dataToEncryptHash } = await encryptFile(
        {
            evmContractConditions,
            chain,
            file,
            authSig
        },
        client
    );

    const filename = (meta.filename || file.name || 'file') + '.enc';
    const mime = meta.mime || 'application/octet-stream';

    const form = new FormData();
    form.append('file', new File([ciphertext], filename, { type: mime }));
    form.append('upload', JSON.stringify({
        filename,
        mime,
        size: ciphertext.size
    }));

    form.append('lit', JSON.stringify({
        dataToEncryptHash,
        evmContractConditions,
        chain
    }));

    const res = await fetch(uploadUrl, { method: 'POST', credentials: 'include', body: form });
    if (!res.ok) {
        const t = await res.text();
        throw new Error(t || 'Upload failed');
    }
    return res.json();
}