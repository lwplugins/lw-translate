/**
 * WordPress dependencies
 */
import { useCallback } from '@wordpress/element';

/**
 * Internal dependencies
 */
import FormSkeleton from './components/FormSkeleton';
import LoadError from './components/LoadError';
import Notices from './components/Notices';
import { api } from './data/api';
import useRemote from './data/useRemote';
import useSettingsStore from './data/useSettingsStore';
import useTranslationActions from './data/useTranslationActions';
import Footer from './shell/Footer';
import navMeta from './shell/navMeta';
import SideNav from './shell/SideNav';
import TopBar from './shell/TopBar';
import { TABS } from './shell/tabs';
import useSaveShortcut from './shell/useSaveShortcut';
import useTab from './shell/useTab';
import useUnsavedWarning from './shell/useUnsavedWarning';
import GeneralTab from './tabs/general/GeneralTab';
import TranslationsTab from './tabs/translations/TranslationsTab';

// Classic links (?page=lw-translate&tab=general) open that tab.
const INITIAL_TAB =
	new URLSearchParams( window.location.search ).get( 'tab' ) ||
	'translations';

/**
 * Shell + the settings store (General) + the translations list, which is
 * loaded once and replaced by the fresh list every action returns.
 */
export default function App() {
	const loadList = useCallback( () => api.translations(), [] );
	const list = useRemote( loadList );
	const actions = useTranslationActions( list );
	const store = useSettingsStore( list.reload );
	const tab = useTab(
		TABS.map( ( t ) => t.id ),
		INITIAL_TAB
	);
	const current = TABS.find( ( t ) => t.id === tab ) || TABS[ 0 ];

	useUnsavedWarning( store.hasEdits );
	useSaveShortcut(
		store.save,
		store.hasEdits && ! store.isSaving,
		!! current.save
	);

	let content;
	if ( current.id === 'translations' ) {
		content = <TranslationsTab list={ list } actions={ actions } />;
	} else if ( store.error ) {
		content = (
			<LoadError message={ store.error } onRetry={ store.reload } />
		);
	} else if ( store.isLoading ) {
		content = <FormSkeleton />;
	} else {
		content = <GeneralTab store={ store } />;
	}

	return (
		<>
			<div className="lw-admin-shell">
				<SideNav
					tabs={ TABS }
					current={ current.id }
					meta={ navMeta( {
						errors: store.errors,
						list: list.data,
					} ) }
					docsUrl={ store.data?.meta.docsUrl }
				/>
				<div className="lw-admin-main">
					<TopBar
						title={ current.title }
						store={ current.save && store.data ? store : null }
					/>
					<main className="lw-admin-scroll">
						<div className="lw-admin-content">{ content }</div>
					</main>
					<Footer />
				</div>
			</div>
			<Notices />
		</>
	);
}
