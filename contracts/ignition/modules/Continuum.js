const { buildModule } = require("@nomicfoundation/hardhat-ignition/modules");

module.exports = buildModule("Continuum", (m) => {
    const admin = m.getAccount(0);
    const submitter = m.contract("SubmitterRegistry", [admin]);
    return { submitter };
});