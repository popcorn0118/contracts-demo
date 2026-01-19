'use strict';
var AmeTableColumnsSettings;
(function (AmeTableColumnsSettings) {
    const _ = wsAmeLodash;
    class Column {
        constructor(id, data, columnVisibilityStrategy) {
            this.id = id;
            this.title = data.title;
            this.present = data.present;
            this.initialPosition = data.position;
            this.visibility = new AmeActorFeatureState(new AmeObservableActorFeatureMap(data.enabledForActor), columnVisibilityStrategy);
            this.canDelete = ko.pureComputed(() => !this.present);
            this.deleteTooltip = 'Delete column "' + this.title + '"';
        }
        toJs() {
            return {
                id: this.id,
                enabledForActor: this.visibility.toJs(),
            };
        }
    }
    class Screen {
        constructor(id, data, customOrderStrategy, columnVisibilityStrategy) {
            this.id = id;
            this.defaultOrder = {};
            this.isOpen = ko.observable(true);
            this.title = data.title;
            _.forEach(data.defaultOrder, (columnId, index) => {
                this.defaultOrder[columnId] = index;
            });
            this.columns = ko.observableArray(Object.entries(data.columns).map(([id, columnData]) => new Column(id, columnData, columnVisibilityStrategy)));
            //Sort the columns by their initial position.
            this.columns.sort((a, b) => a.initialPosition - b.initialPosition);
            this.customColumnOrder = new AmeActorFeatureState(new AmeObservableActorFeatureMap(data.customOrderEnabled), customOrderStrategy);
            //The columns count as being in the default order if all the "default" columns
            //are in the correct order. Any other columns (e.g. columns that no longer exist)
            //can be in any order.
            this.isDefaultOrder = ko.pureComputed(() => this.columns().every((column, index) => {
                const defaultIndex = this.defaultOrder[column.id];
                return (typeof defaultIndex === 'undefined') || (index === defaultIndex);
            }));
            this.canDelete = ko.pureComputed(() => !data.probablyExists);
        }
        toJs() {
            const columns = this.columns();
            const columnsById = {};
            for (let index = 0; index < this.columns().length; index++) {
                const column = columns[index];
                columnsById[column.id] = {
                    ...column.toJs(),
                    position: index
                };
            }
            return {
                id: this.id,
                customOrderEnabled: this.customColumnOrder.toJs(),
                columns: columnsById
            };
        }
        resetOrder() {
            if (this.isDefaultOrder()) {
                alert('The columns are already in the default order.');
                return;
            }
            this.columns.sort((a, b) => {
                const defaultIndexA = this.defaultOrder[a.id];
                const defaultIndexB = this.defaultOrder[b.id];
                if (typeof defaultIndexA === 'undefined') {
                    return 1;
                }
                if (typeof defaultIndexB === 'undefined') {
                    return -1;
                }
                return defaultIndexA - defaultIndexB;
            });
        }
        deleteColumn(column) {
            if (!column.canDelete()) {
                alert('You cannot delete this column.');
                return;
            }
            this.columns.remove(column);
        }
        toggle() {
            this.isOpen(!this.isOpen());
        }
    }
    class TableColumnsSettingsVm {
        constructor(scriptData) {
            const actorSelector = new AmeActorSelector(AmeActors, true, true);
            const selectedActor = actorSelector.createActorObservable(ko);
            const allActors = ko.pureComputed(() => {
                return actorSelector.getVisibleActors();
            });
            //Reselect the previously selected actor.
            if (scriptData.selectedActor && AmeActors.actorExists(scriptData.selectedActor)) {
                selectedActor(AmeActors.getActor(scriptData.selectedActor));
            }
            const customOrderStrategy = new AmeActorFeatureStrategy({
                ...ameUnserializeFeatureStrategySettings(scriptData.orderStrategy),
                getSelectedActor: selectedActor,
                getAllActors: allActors
            });
            const columnVisibilityStrategy = new AmeActorFeatureStrategy({
                ...ameUnserializeFeatureStrategySettings(scriptData.columnVisibilityStrategy),
                getSelectedActor: selectedActor,
                getAllActors: allActors
            });
            this.screens = ko.observableArray(Object.entries(scriptData.screens).map(([id, screenData]) => new Screen(id, screenData, customOrderStrategy, columnVisibilityStrategy)));
            //Sort the screens alphabetically.
            this.screens.sort((a, b) => a.title.localeCompare(b.title));
            this.saveSettingsForm = new AmeKoFreeExtensions.SaveSettingsForm({
                ...scriptData.saveFormConfig,
                settingsGetter: () => {
                    return {
                        screens: _.keyBy(this.screens().map(screen => screen.toJs()), 'id')
                    };
                },
                selectedActor: selectedActor
            });
            //Remember which sections (screens) are open.
            const openScreenIds = ko.computed({
                read: () => {
                    return this.screens().filter(screen => screen.isOpen()).map(screen => screen.id);
                },
                write: (value) => {
                    this.screens().forEach(screen => {
                        screen.isOpen(value.includes(screen.id));
                    });
                }
            });
            const openScreensCookie = new WsAmePreferenceCookie('ame_tc_open_screens', 90, true, scriptData.preferenceCookiePath);
            const initialOpenScreenIds = openScreensCookie.readAndRefresh(null);
            if ((initialOpenScreenIds !== null) && (Array.isArray(initialOpenScreenIds))) {
                openScreenIds(initialOpenScreenIds);
            }
            else {
                //Open the first screen by default, if there is one.
                const firstScreen = this.screens()[0];
                if (firstScreen) {
                    openScreenIds([firstScreen.id]);
                }
                else {
                    openScreenIds([]);
                }
            }
            //Rate limit to avoid too many cookie writes.
            openScreenIds.extend({ rateLimit: { timeout: 1000, method: 'notifyWhenChangesStop' } });
            openScreenIds.subscribe((screenIds) => {
                openScreensCookie.write(screenIds);
            });
        }
        deleteScreen(screen) {
            if (screen.canDelete()) {
                this.screens.remove(screen);
            }
            else {
                alert('You cannot delete this screen.');
            }
        }
    }
    jQuery(function () {
        const settingsVm = new TableColumnsSettingsVm(wsAmeTableColumnsSettingsData);
        ko.applyBindings(settingsVm, jQuery('#ame-tc-settings-page-container')[0]);
    });
})(AmeTableColumnsSettings || (AmeTableColumnsSettings = {}));
//# sourceMappingURL=table-columns.js.map