import json
import os
from pathlib import Path
import sys
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).parents[1]))
from release_v1.proof import qa_engine_proof, Blocked, Throttled

class QAEngineProof(unittest.TestCase):
    def test_provider_and_signing_credentials_do_not_reach_qa_and_exact_receipt_is_required(self):
        with tempfile.TemporaryDirectory() as temp:
            output=Path(temp)/'proof'
            request={'release_id':'a'*64,'source_sha':'b'*40,'artifact_sha256':'c'*64,'urls':['https://example.org/']}
            receipt={**request,'status':'passed','qa_engine_sha':'d'*40}
            def run(argv, **kwargs):
                self.assertEqual(request,json.loads(Path(argv[2]).read_text()))
                for key in ('MRN_RELEASE_RECEIPT_KEY','DEPLOY_WPENGINE_API_PASSWORD','SSH_AUTH_SOCK','AWS_SECRET_ACCESS_KEY'):
                    self.assertNotIn(key,kwargs['env'])
                (output/'release-proof.json').write_text(json.dumps(receipt))
                return SimpleNamespace(returncode=0)
            with patch.dict(os.environ,{'MRN_RELEASE_RECEIPT_KEY':'secret','DEPLOY_WPENGINE_API_PASSWORD':'secret',
                                        'SSH_AUTH_SOCK':'agent','AWS_SECRET_ACCESS_KEY':'secret'}), \
                    patch('release_v1.proof.subprocess.run',side_effect=run):
                result=qa_engine_proof('/protected/qa',request,output)
                self.assertTrue(result['passed']);self.assertEqual(result['qa_engine_sha'],'d'*40)
                receipt['release_id']='e'*64
                with self.assertRaises(Blocked):qa_engine_proof('/protected/qa',request,output)

    def test_structured_throttle_is_forwarded_to_signed_controller_receipt(self):
        with tempfile.TemporaryDirectory() as temp:
            output=Path(temp);request={'release_id':'a'*64,'source_sha':'b'*40,'artifact_sha256':'c'*64,'urls':['https://example.org/']}
            (output/'release-proof.json').write_text(json.dumps({**request,'status':'throttled','http_status':429,'retry_after':'60'}))
            with patch('release_v1.proof.subprocess.run',return_value=SimpleNamespace(returncode=2)):
                with self.assertRaises(Throttled) as caught:qa_engine_proof('/protected/qa',request,output)
            self.assertEqual(caught.exception.retry_after,'60')

if __name__=='__main__':unittest.main()
