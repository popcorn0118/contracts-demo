import { KoStandaloneControl } from '../control-base.js';
/**
 * Base class for description components.
 */
export class AmeDescriptionComponent extends KoStandaloneControl {
    constructor(params, $element) {
        super(params, $element);
        this.description = params.description || '';
    }
}
//# sourceMappingURL=ame-description.js.map