/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, external, update } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { SkeletonText } from '../../components/skeleton';
import { ago, toneLabel } from '../../data/format';

/**
 * Toolbar above the table: where the translations come from, how old the
 * cached repository listing is, and "Refresh".
 *
 * @param {Object}      props
 * @param {Object|null} props.data      Translations list.
 * @param {string}      props.busy      Busy key.
 * @param {Function}    props.onRefresh Refresh.
 */
export default function SourceBar( { data, busy, onRefresh } ) {
	if ( ! data ) {
		return (
			<div className="lw-translate-sourcebar" aria-hidden="true">
				<SkeletonText width="40%" />
			</div>
		);
	}

	const { source, cache } = data;
	let age = __( 'Not cached yet', 'lw-translate' );
	if ( cache.fetchedAt ) {
		age = sprintf(
			/* translators: %s: relative time, e.g. "5 minutes ago". */
			__( 'Repository listing fetched %s', 'lw-translate' ),
			ago( cache.fetchedAt )
		);
	}

	return (
		<div className="lw-translate-sourcebar">
			<div className="lw-admin-stack">
				<span>
					{ sprintf(
						/* translators: 1: tone ("Formal"/"Informal"), 2: locale code. */
						__( 'Source: %1$s / %2$s', 'lw-translate' ),
						toneLabel( source.tone ),
						source.locale
					) }
					{ source.url && (
						<>
							{ ' · ' }
							<a
								href={ source.url }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ `${ source.repo } (${ source.branch })` }
								<Icon icon={ external } size={ 14 } />
								<span className="screen-reader-text">
									{ __(
										'(opens in a new tab)',
										'lw-translate'
									) }
								</span>
							</a>
						</>
					) }
				</span>
				<span className="lw-admin-hint">{ age }</span>
			</div>
			<Button
				__next40pxDefaultSize
				variant="secondary"
				icon={ update }
				isBusy={ busy === 'refresh' }
				disabled={ !! busy || ! data.canInstall }
				accessibleWhenDisabled
				onClick={ onRefresh }
			>
				{ __( 'Refresh', 'lw-translate' ) }
			</Button>
		</div>
	);
}
