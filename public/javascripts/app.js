import Form from "./pulsar/modules/form.js";
import { initTheme } from './pulsar/modules/theme.js';

export default class Application {

    #formEngine;
    #configurations;
    i18n;

    constructor(configurations) {
        this.#configurations = configurations;
        this.i18n = configurations.i18n;
    }

    initialize() {
        initTheme();
        this.#initializeForms();
    }

    getFormEngine() {
        return this.#formEngine;
    }

    #initializeForms() {
        this.#formEngine = new Form(this.i18n.buttons.loading);
        this.#formEngine.preventDoubleSubmission();
        this.#formEngine.autoResizeTextarea();
    }
}
