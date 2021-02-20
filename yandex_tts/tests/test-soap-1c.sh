#
# MikoPBX - free phone system for small business
# Copyright © 2017-2021 Alexey Portnov and Nikolay Beketov
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along with this program.
# If not, see <https://www.gnu.org/licenses/>.
#
dirName=$(dirname "$(dirname "$0")");
cfgFile="${dirName}/setting.json";

if [ ! -f "$cfgFile" ]; then
  echo "Settings $cfgFile file not found";
  exit 2;
fi

serviceUri="\"$(grep 'crm-uri-soap' < "${cfgFile}" | cut -d '"' -f 4)\"";
funcName=$(grep 'crm-function-soap' < "${cfgFile}" | cut -d '"' -f 4);
number='79032147962';
did='79257184255';

header='<?xml version="1.0" encoding="UTF-8"?>';
envelopUri='"http://schemas.xmlsoap.org/soap/envelope/"';

body="${header}
  <soap:Envelope xmlns:soap=${envelopUri}>
    <soap:Body>
      <m:${funcName} xmlns:m=${serviceUri}>
        <m:Number>${number}</m:Number>
        <m:DID>${did}</m:DID>
        <m:Linkedid>${number}</m:Linkedid>
      </m:${funcName}>
    </soap:Body>
</soap:Envelope>";

crmAuth=$(grep 'crm-http-auth' < "${cfgFile}" | cut -d '"' -f 4);
crmUrl=$( grep 'crm-url-soap'  < "${cfgFile}" | cut -d '"' -f 4);
# Проверка авторизации.
curl -u "$crmAuth" --header "Content-Type: text/xml; charset=utf-8" "${crmUrl}" -I
# Запрос данных web сервиса.
curl -u "$crmAuth" --header "Content-Type: text/xml; charset=utf-8" -d "${body}" "${crmUrl}"