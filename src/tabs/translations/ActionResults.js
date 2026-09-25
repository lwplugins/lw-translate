/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ResultBox from '../../components/ResultBox';
import { typeLabel } from '../../data/format';

/**
 * Summary line of a finished run.
 *
 * @param {Object} result { action, items, failure }.
 * @return {string} Message.
 */
function summary( result ) {
	const ok = result.items.filter( ( r ) => r.ok ).length;
	const failed = result.items.length - ok;
	const done =
		result.action === 'install'
			? sprintf(
					/* translators: %d: number of translations. */
					_n(
						'%d translation installed.',
						'%d translations installed.',
						ok,
						'lw-translate'
					),
					ok
				)
			: sprintf(
					/* translators: %d: number of translations. */
					_n(
						'%d translation deleted.',
						'%d translations deleted.',
						ok,
						'lw-translate'
					),
					ok
				);

	if ( ! failed ) {
		return done;
	}

	return `${ done } ${ sprintf(
		/* translators: %d: number of failed items. */
		_n( '%d failed.', '%d failed.', failed, 'lw-translate' ),
		failed
	) }`;
}

/**
 * Per-item outcome of the last install/update/delete run.
 *
 * @param {Object}   props
 * @param {Object}   props.result    { action, items, failure }.
 * @param {Function} props.onDismiss Hide the box.
 */
export default function ActionResults( { result, onDismiss } ) {
	const failed = result.items.some( ( r ) => ! r.ok );
	let tone = 'ok';
	if ( result.failure || failed ) {
		tone = result.items.some( ( r ) => r.ok ) ? 'warning' : 'error';
	}

	return (
		<ResultBox
			tone={ tone }
			message={ summary( result ) }
			details={ result.items.map( ( r ) => (
				<span key={ `${ r.type }:${ r.slug }` }>
					<strong>{ r.name }</strong>{ ' ' }
					<span className="lw-admin-hint">
						({ typeLabel( r.type ) })
					</span>
					{ ': ' }
					<span className={ r.ok ? '' : 'lw-translate-failed' }>
						{ r.message }
					</span>
				</span>
			) ) }
		>
			{ result.failure && (
				<p className="lw-translate-failed">{ result.failure }</p>
			) }
			<div>
				<Button variant="link" onClick={ onDismiss }>
					{ __( 'Dismiss', 'lw-translate' ) }
				</Button>
			</div>
		</ResultBox>
	);
}
