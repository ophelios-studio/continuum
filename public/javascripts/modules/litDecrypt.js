import { LitNodeClient } from 'https://esm.sh/@lit-protocol/lit-node-client@7';
import { LIT_NETWORK } from 'https://esm.sh/@lit-protocol/constants@7';
import { decryptToFile } from 'https://esm.sh/@lit-protocol/encryption@7';
import { getAuthSigSiwe } from "./wallet.js"

export async function decryptEvidenceFile({ metaUrl, downloadUrl }) {
    if (!metaUrl || !downloadUrl) throw new Error('metaUrl and downloadUrl are required');
    if (!window.ethereum) throw new Error('Wallet provider not found');
    await window.ethereum.request({ method: 'eth_requestAccounts' });

    const metaRes = await fetch(metaUrl, { credentials: 'include' });
    if (!metaRes.ok) throw new Error((await metaRes.text()) || 'Meta fetch failed');
    const meta = await metaRes.json();

    const fileRes = await fetch(downloadUrl, { credentials: 'include' });
    if (!fileRes.ok) throw new Error((await fileRes.text()) || 'File fetch failed');
    const ciphertextBlob = await fileRes.blob();

    const {
        filename = 'file.enc',
        mime_type = 'application/octet-stream',
        lit: {
            dataToEncryptHash,
            evmContractConditions,
            accessControlConditions,
            unifiedAccessControlConditions,
            chain
        } = {}
    } = meta;

    if (!dataToEncryptHash) throw new Error('Missing dataToEncryptHash in metadata');

    const client = new LitNodeClient({ litNetwork: LIT_NETWORK.Datil });
    await client.connect();

    const authSig = await getAuthSigSiwe({ chain });
    console.log(authSig);

    const acc =
        evmContractConditions
            ? { evmContractConditions }
            : (unifiedAccessControlConditions
                ? { unifiedAccessControlConditions }
                : { accessControlConditions });

    const ciphertextBase64 = await blobToBase64(ciphertextBlob);
    const payload = {
        ...acc,
        chain,
        ciphertext: ciphertextBase64,
        dataToEncryptHash,
        authSig
    };

    const { decryptedFile } = await decryptToFile(payload, client);
    return {
        blob: decryptedFile,
        filename: filename.replace(/\.enc$/i, ''),
        mime: mime_type
    };
}

export function saveBlob({ blob, filename = 'file' }) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => {
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }, 0);
}

function blobToBase64(blob) {
    return new Promise((resolve, reject) => {
        const r = new FileReader();
        r.onloadend = () => {
            const res = r.result || '';
            // r.result is a data URL: "data:...;base64,AAAA"
            const base64 = String(res).includes(',') ? String(res).split(',')[1] : String(res);
            resolve(base64);
        };
        r.onerror = reject;
        r.readAsDataURL(blob);
    });
}
