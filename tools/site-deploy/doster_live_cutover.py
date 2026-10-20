"""Prepare the exact owner-approved Doster cutover from a manual main workflow."""
import argparse
import json
import os
from pathlib import Path

from kinsta import DOSTER_REPOSITORY, DOSTER_SITE, DOSTER_LIVE, DOSTER_LIVE_TARGET, DOSTER_MANUAL_APPROVAL
from promoted_store import DOSTER_CUTOVER_ORDER, reviewed_doster_promotion
from release_request import api
from verify_release import verify

ORIGINAL_ARTIFACT_ID = '12272493310'


def prepare(environ, archive):
    expected = dict(GITHUB_ACTIONS='true', GITHUB_EVENT_NAME='workflow_dispatch', GITHUB_REF='refs/heads/main',
                    GITHUB_REPOSITORY=DOSTER_REPOSITORY, QUALIFY_DOSTER_LIVE='true', TARGET='live',
                    GITHUB_WORKFLOW_REF=DOSTER_REPOSITORY+'/.github/workflows/site-deploy.yml@refs/heads/main',
                    SOURCE_BRANCH='main', SOURCE_PATH='.', STYLESHEET='doster-and-brown-child',
                    DEPLOY_READY='0', LIVE_ENABLED='true', DEPLOY_HOST_PROVIDER='kinsta', DEPLOY_BACKUP_PROVIDER='kinsta',
                    DEPLOY_KINSTA_SITE_ID=DOSTER_SITE, DEPLOY_KINSTA_ENVIRONMENT_ID=DOSTER_LIVE,
                    DEPLOY_KINSTA_MANUAL_BACKUP_APPROVAL=DOSTER_MANUAL_APPROVAL,
                    DEPLOY_KINSTA_TRANSACTION_BACKUP_APPROVED='1')
    expected.update({'DEPLOY_'+k.upper():v for k,v in DOSTER_LIVE_TARGET.items() if k!='environment'})
    if (any(environ.get(k)!=v for k,v in expected.items()) or environ.get('MODE') not in ('preflight','deploy')
            or environ.get('QUALIFY_DEV')=='true'
            or (environ.get('MODE')=='deploy' and environ.get('MRN_SOURCE_QA_SHA')!=environ.get('SOURCE_SHA'))):
        raise ValueError('Doster cutover requires the exact disarmed Live target and manual main workflow')
    if api(environ,'git/ref/heads/main')['object']['sha']!=environ.get('SOURCE_SHA'):
        raise ValueError('Doster qualification source is no longer current main')
    run=api(environ,'actions/runs/'+DOSTER_CUTOVER_ORDER['run_id'])
    if (not isinstance(run,dict) or not isinstance(run.get('repository'),dict)
            or run.get('id')!=int(DOSTER_CUTOVER_ORDER['run_id']) or run.get('conclusion')!='success'
            or run.get('status')!='completed' or run.get('event')!='workflow_dispatch'
            or run.get('head_sha')!=DOSTER_CUTOVER_ORDER['source_sha']
            or run.get('path')!='.github/workflows/site-deploy.yml'
            or run.get('repository',{}).get('full_name')!=DOSTER_REPOSITORY
            or any(run.get(k)!=DOSTER_CUTOVER_ORDER[k] for k in ('run_number','run_attempt'))):
        raise ValueError('Original Doster qualification run provenance changed')
    artifact=api(environ,'actions/artifacts/'+ORIGINAL_ARTIFACT_ID)
    if (not isinstance(artifact,dict) or not isinstance(artifact.get('workflow_run'),dict)
            or artifact.get('id')!=int(ORIGINAL_ARTIFACT_ID) or artifact.get('expired') is not False
            or artifact.get('name')!='site-release-'+DOSTER_CUTOVER_ORDER['source_sha']
            or artifact.get('workflow_run',{}).get('id')!=int(DOSTER_CUTOVER_ORDER['run_id'])
            or artifact.get('workflow_run',{}).get('head_sha')!=DOSTER_CUTOVER_ORDER['source_sha']):
        raise ValueError('Original Doster build artifact provenance changed')
    promotion=reviewed_doster_promotion()
    verify(archive,promotion['pointer']['release_id'],promotion['source_sha'],'.','doster-and-brown-child')
    return promotion


if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--archive',required=True)
    parser.add_argument('--output',required=True)
    args=parser.parse_args()
    Path(args.output).write_text(json.dumps(prepare(os.environ,args.archive),indent=2)+'\n')
