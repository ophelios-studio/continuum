import { buildModule } from "@nomicfoundation/hardhat-ignition/modules";

export default buildModule("Continuum", (m) => {
    const admin = m.getAccount(0);
    const submitter = m.contract("SubmitterRegistry", [admin]);
    return { submitter };
});