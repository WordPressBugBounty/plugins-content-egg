function cfg() {
	return window.ceggPmConfig || {};
}

export function clicksEnabled() {
	return !! cfg().clicksEnabled;
}

export function clicksLabel30() {
	return cfg().clicksLabel30 || '30d';
}
