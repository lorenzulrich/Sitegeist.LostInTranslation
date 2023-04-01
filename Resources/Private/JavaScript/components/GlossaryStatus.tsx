import * as React from 'react';

interface GlossaryStatusProps {
    data: {};
    translate: (id: string, label: string, args?: any[]) => string;
}

export default function GlossaryStatus({ data, translate }: GlossaryStatusProps) {
    return (
        <React.Fragment>
            <h2 className="entries-list__header">{translate('status.headline', 'Glossary status')}</h2>
            <table class="neos-table entries-table glossary-status-table">
                <thead>
                    <tr>
                        <th>{translate('status.glossary', 'Glossary')}</th>
                        <th>{translate('status.lastUpdate', 'Last update')}</th>
                        <th>{translate('status.usable', 'Usable?')}</th>
                    </tr>
                </thead>
                <tbody>
                    {Object.keys(data).map((key) => {
                        const glossary = data[key];
                        const canBeUsed = glossary['canBeUsed'];
                        return (
                            <tr>
                                <td>{glossary['sourceLang'] + ' --> ' + glossary['targetLang']}</td>
                                <td className={glossary['isOutdated'] ? 'isOutdated' : 'isUpToDate'}>
                                    ⬤ {glossary['creationDate']}
                                </td>
                                <td className={canBeUsed ? 'canBeUsed' : 'canNotBeUsed'}>
                                    {canBeUsed ? translate('status.usable.yes', 'yes') : translate('status.usable.no', 'no')}
                                </td>
                            </tr>
                        )
                    })}
                </tbody>
            </table>
            <table className="neos-table entries-table glossary-status-table">
                <thead>
                    <tr>
                        <th>{translate('status.legend', 'Legend')}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td className="isUpToDate">
                            ⬤ = {translate('status.legend.isUpToDate', 'DeepL glossary is up to date')}
                        </td>
                        <td className="isOutdated">
                            ⬤ = {translate('status.legend.isOutdated', 'DeepL glossary is not yet up to date')}
                        </td>
                    </tr>
                </tbody>
            </table>
        </React.Fragment>
    );
}
