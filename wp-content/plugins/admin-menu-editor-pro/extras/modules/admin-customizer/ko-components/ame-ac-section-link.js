import { createComponentConfig, KoContainerViewModel } from '../../../pro-customizables/ko-components/control-base.js';
import { AmeCustomizable } from '../../../pro-customizables/assets/customizable.js';
var Section = AmeCustomizable.Section;
import { AmeAcSection } from './ame-ac-section.js';
class AmeAcSectionLink extends KoContainerViewModel {
    constructor(params, $element) {
        super(params, $element);
        this.targetElementId = AmeAcSection.getSectionElementId(this);
        this.elementId = AmeAcSection.getSectionLinkElementId(this);
    }
    getExpectedUiElementType() {
        return Section;
    }
}
export default createComponentConfig(AmeAcSectionLink, `
	<li class="ame-ac-section-link" data-bind="attr: {'data-target-id' : targetElementId, 'id': elementId}">
		<h3 class="ame-ac-section-title" data-bind="text: title"></h3>
	</li>
`);
//# sourceMappingURL=ame-ac-section-link.js.map