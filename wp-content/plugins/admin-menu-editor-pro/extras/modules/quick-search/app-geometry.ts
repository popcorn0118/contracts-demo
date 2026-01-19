import none = AmeMiniFunc.none;
import some = AmeMiniFunc.some;
import Option = AmeMiniFunc.Option;

import Cookies from 'js-cookie';

const geometryCookieName = 'ameQuickSearchGeometry';

interface GeometryCookieShape {
	size?: { width: number, height: number };
	position?: {
		horizontal: { side: 'left' | 'right', offset: number },
		vertical: { side: 'top' | 'bottom', offset: number }
	}
}

function parseGeometryCookie(cookieValue: string): Option<GeometryCookieShape> {
	function validatePositionAxis(value: unknown, axis: 'horizontal' | 'vertical'): boolean {
		if (!value || typeof value !== 'object') {
			return false;
		}
		const position = value as Record<string, unknown>;
		if (typeof position.side !== 'string' || typeof position.offset !== 'number') {
			return false;
		}
		if (axis === 'horizontal') {
			return position.side === 'left' || position.side === 'right';
		} else {
			return position.side === 'top' || position.side === 'bottom';
		}
	}

	try {
		const parsed = JSON.parse(cookieValue);
		if (!parsed || typeof parsed !== 'object') {
			return none;
		}

		//At least one of "size" and "position" must be present. The cookie can
		//technically be valid without either, but it would be useless.
		if (!parsed.size && !parsed.position) {
			return none;
		}

		//If "size" is present, it must be an object with "width" and "height" properties.
		if (parsed.size) {
			const size = parsed.size;
			if (typeof size === 'object' && size.width && size.height) {
				if (typeof size.width === 'number' && typeof size.height === 'number') {
					//Size is valid.
				} else {
					return none;
				}
			} else {
				return none;
			}
		}

		//If "position" is present, it must also have the correct shape.
		if (parsed.position) {
			const position = parsed.position;
			if (
				!validatePositionAxis(position.horizontal, 'horizontal')
				|| !validatePositionAxis(position.vertical, 'vertical')
			) {
				return none;
			}
		}

		return some(parsed);
	} catch (e) {
		return none;
	}
}

export class PanelGeometryStore {
	constructor(private readonly cookiePath: string) {
	}

	storeSize(size: { width: number, height: number }) {
		const value = this.getOrCreateGeometry();
		value.size = size;
		this.updateCookie(value);
	}

	storePosition(top: number, left: number, width: number, height: number) {
		const value = this.getOrCreateGeometry();
		const centerX = left + width / 2;
		const centerY = top + height / 2;

		const $window = jQuery(window);
		const windowWidth = $window.width() || 0;
		const windowHeight = $window.height() || 0;

		const horizontalSide = centerX < windowWidth / 2 ? 'left' : 'right';
		const horizontalOffset = (horizontalSide === 'left') ? left : -(windowWidth - left - width);

		const verticalSide = centerY < windowHeight / 2 ? 'top' : 'bottom';
		const verticalOffset = (verticalSide === 'top') ? top : -(windowHeight - top - height);

		value.position = {
			horizontal: {
				side: horizontalSide,
				offset: horizontalOffset
			},
			vertical: {
				side: verticalSide,
				offset: verticalOffset
			}
		};
		this.updateCookie(value);
	}

	getStoredSize(): Option<{ width: number, height: number }> {
		const value = this.getStoredGeometry();
		return value.flatMap(
			(geometry) => geometry.size ? some(geometry.size) : none
		);
	}

	getStoredPositionForJQuery(): Option<{ my: string, at: string }> {
		return this.getStoredGeometry()
			.flatMap(
				(geometry) => geometry.position ? some(geometry.position) : none
			)
			.map(
				(geometry) => {
					const my = geometry.horizontal.side + ' ' + geometry.vertical.side;

					const at: string = (
						geometry.horizontal.side
						+ ((geometry.horizontal.offset >= 0) ? '+' : '-')
						+ Math.abs(geometry.horizontal.offset)
						+ ' '

						+ geometry.vertical.side
						+ ((geometry.vertical.offset >= 0) ? '+' : '-')
						+ Math.abs(geometry.vertical.offset)
					);

					return {my, at};
				}
			);
	}

	private getStoredGeometry(): Option<GeometryCookieShape> {
		const cookieValue = Cookies.get(geometryCookieName);
		if (cookieValue) {
			const result = parseGeometryCookie(cookieValue);
			if (result.isDefined()) {
				return result;
			} else {
				//Delete invalid cookie.
				Cookies.remove(geometryCookieName, {path: this.cookiePath});
			}
		}
		return none;
	}

	private updateCookie(value: GeometryCookieShape) {
		console.log('Storing geometry:', value);

		Cookies.set(geometryCookieName, JSON.stringify(value), {
			expires: 90,
			path: this.cookiePath,
			sameSite: 'Lax'
		});
	}

	private getOrCreateGeometry(): GeometryCookieShape {
		return this.getStoredGeometry().getOrElse(() => ({}));
	}
}