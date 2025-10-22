import { buildModule } from "@nomicfoundation/hardhat-ignition/modules";

export default buildModule("Continuum", (m) => {
    const admin = m.getAccount(0);
    const submitter = m.contract("SubmitterRegistry", [admin]);
    const evidence = m.contract("EvidenceRegistry", [admin, submitter]);
    return { submitter, evidence };
});