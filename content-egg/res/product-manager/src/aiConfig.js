function cfg() {
	return window.ceggPmConfig || {};
}

export function aiEnabled() {
	return !! cfg().aiEnabled;
}

export function aiTitleMethods() {
	return Array.isArray( cfg().aiTitleMethods ) ? cfg().aiTitleMethods : [];
}

export function aiDescriptionMethods() {
	return Array.isArray( cfg().aiDescriptionMethods )
		? cfg().aiDescriptionMethods
		: [];
}

export function smartGroupMethods() {
	return Array.isArray( cfg().smartGroupMethods )
		? cfg().smartGroupMethods
		: [];
}
