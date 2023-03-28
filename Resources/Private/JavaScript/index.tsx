import * as React from 'react';
import * as ReactDOM from 'react-dom';

import { Glossary } from './components';
import { GlossaryProvider, IntlProvider } from './providers';

import '../Styles/styles.scss';

window.onload = async (): Promise<void> => {
    let NeosAPI = window.Typo3Neos || window.NeosCMS;

    while (!NeosAPI || !NeosAPI.I18n || !NeosAPI.I18n.initialized) {
        NeosAPI = window.NeosCMS || window.Typo3Neos;
        await new Promise((resolve) => setTimeout(resolve, 50));
    }

    const glossaryApp: HTMLElement = document.getElementById('glossary-app');
    const glossaryData: HTMLElement = document.getElementById('glossary-data');

    if (!glossaryApp || !glossaryData) {
        return;
    }

    const entries: {} = JSON.parse(glossaryData.innerText);
    const languages: string[] = JSON.parse(glossaryApp.dataset.languages);

    // ToDo remove logging
    console.log('entries');
    console.log(entries);
    console.log('languages');
    console.log(languages);

    const { csrfToken } = glossaryApp.dataset;
    const actions: {
        delete: string;
        create: string;
        update: string;
    } = JSON.parse(glossaryApp.dataset.actions);
    const { I18n, Notification } = NeosAPI;

    /**
     * @param id
     * @param label
     * @param args
     */
    const translate = (id: string, label = '', args = []): string => {
        return I18n.translate(id, label, 'Sitegeist.LostInTranslation', 'GlossaryModule', args);
    };

    ReactDOM.render(
        <GlossaryProvider value={{ csrfToken }}>
            <IntlProvider translate={translate}>
                <Glossary
                    entries={entries}
                    languages={languages}
                    actions={actions}
                    translate={translate}
                    notificationHelper={Notification}
                />
            </IntlProvider>
        </GlossaryProvider>,
        glossaryApp,
    );
};
