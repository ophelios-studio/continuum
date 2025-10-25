import { expect } from "chai";
import { network } from "hardhat";
import type { SubmitterRegistry } from "../types/ethers-contracts/index.js";
import {describe, it} from "node:test";

async function getEthers() {
  const { ethers } = await network.connect();
  return ethers;
}

describe("SubmitterRegistry", function () {
  const roleHash = (ethers: any, s: string) => ethers.id(s);

  async function deploy() {
    const ethers = await getEthers();
    const [admin, validator, user1, user2, stranger] = await ethers.getSigners();

    const Factory = await ethers.getContractFactory("SubmitterRegistry");
    const registry = (await Factory.deploy(admin.address)) as SubmitterRegistry;

    await registry.connect(admin).grantRole(roleHash(ethers, "VALIDATOR"), validator.address);

    return { ethers, registry, admin, validator, user1, user2, stranger };
  }

  it("sets up roles", async function () {
    const { registry, admin } = await deploy();

    expect(await registry.hasRole(await registry.DEFAULT_ADMIN_ROLE(), admin.address)).to.equal(true);
    const ethers = await getEthers();
    expect(await registry.hasRole(roleHash(ethers, "ADMIN"), admin.address)).to.equal(true);
    expect(await registry.hasRole(roleHash(ethers, "VALIDATOR"), admin.address)).to.equal(true);
  });

  it("allows users to anchor profile", async function () {
    const { ethers, registry, user1 } = await deploy();

    const profileHash = ethers.hexlify(ethers.randomBytes(32));
    const jurisdiction = "QC-CA";

    await expect(registry.connect(user1).registerSubmitter(profileHash as `0x${string}`, jurisdiction))
      .to.emit(registry, "SubmitterRegistered")
      .withArgs(user1.address, profileHash, jurisdiction);

    const level = await registry.roleLevel(user1.address);
    // RoleLevel.DECLARED = 1
    expect(level).to.equal(1n);

    const s = await registry.submitters(user1.address);
    expect(s.profileHash).to.equal(profileHash);
    expect(s.jurisdiction).to.equal(jurisdiction);
    expect(s.verifiedUntil).to.equal(0n);
    expect(s.orgId).to.equal(0n);
  });

  it("prevents double registration", async function () {
    const { ethers, registry, user1 } = await deploy();
    const profileHash = ethers.hexlify(ethers.randomBytes(32));

    await registry.connect(user1).registerSubmitter(profileHash as `0x${string}`, "QC-CA");
    await expect(
      registry.connect(user1).registerSubmitter(profileHash as `0x${string}`, "QC-CA")
    ).to.be.revertedWith("Already registered");
  });
});
