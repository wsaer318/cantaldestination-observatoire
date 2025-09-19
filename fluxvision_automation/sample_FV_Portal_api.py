import requests
from requests.auth import HTTPBasicAuth
import yaml
import logging
logging.basicConfig(level=logging.DEBUG)

config_file = 'sample_FV_Portal_api_conf.yml'

with open(config_file) as cf:
    config = yaml.load(cf, Loader=yaml.FullLoader)

login=config['LOGIN']
passwd = config['PASSWD']

auth = HTTPBasicAuth(login, passwd)

API_URL = config['API_URL']
ORGA_CODE_REF = config['ORGA_CODE_REF']
STUDY_CODE_REF = config['STUDY_CODE_REF']
DELIVERY_CODE_REF = config['STUDY_CODE_REF']

r = requests.get(API_URL+'/'+ORGA_CODE_REF+'/', auth=auth)
logging.debug(f'Status code : {r.status_code}')
results = r.json()
logging.debug(f'Number of deliveries : {len(results)}')
logging.debug(results)
for r in results:
    logging.info(f"{r['delivery_code_ref']} - {r['file_last_modified_date']} - {r['file_name']}")