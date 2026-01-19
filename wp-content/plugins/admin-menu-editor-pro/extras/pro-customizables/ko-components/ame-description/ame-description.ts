import {KoStandaloneControl} from '../control-base.js';

/**
 * Base class for description components.
 */
export class AmeDescriptionComponent extends KoStandaloneControl {
	public readonly description: string;

	constructor(params: any, $element: JQuery) {
		super(params, $element);
		this.description = params.description || '';
	}
}