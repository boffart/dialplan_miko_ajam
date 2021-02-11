#!/bin/sh

downloadFile ()
{
  curl "https://${2}/admin/1c/download/index.php?type=Records&view=${filePath}" --insecure -o "${resDir}/${fileName}" 2>/dev/null;
  resultCurl="$?";
  if [ ! "$resultCurl" = '0' ]; then
    echo "Can not get file '${filePath}'. Curl status ${resultCurl}"
    endProgramm "$@";
  fi
  soxi "${resDir}/${fileName}" 2>/dev/null >/dev/null;
  resultSoxi="$?";
}

getFile ()
{
  # get path to wav file
  filePath=$(head -n 1 "${1}");
  resDir=$(dirname "${monDir}/${filePath}");
  fileName=$(basename "${filePath}");

  echo -ne "Downloading $i \\ $countRows : $filePath ... \r";
  mkdir -p "${resDir}";

  downloadFile "$@";
  if [ ! "$resultSoxi" = '0' ]; then
    filePath=$(head -n 1 "${1}" | cut -d'.' -f1-2);
    filePath="${filePath}.mp3";
    orgFileName="${fileName}";
    fileName=$(basename "${filePath}");

    downloadFile "$@";
    mv "${resDir}/${fileName}" "${resDir}/${orgFileName}";
    fileName="${orgFileName}";
    isMP3="0";
  fi

  if [ ! "$resultSoxi" = '0' ]; then
    echo "${filePath}" >> fail-download.log;
  else
    if [ ! "$isMP3" = "0" ]; then
      soxi "${resDir}/${fileName}" | grep 'MPEG' > /dev/null;
      isMP3="$?";
    fi
    if [ "$isMP3" = "0" ]; then
      mv "${resDir}/${fileName}" "${resDir}/${fileName}.mp3"
    else
      lame -b 32 --silent "${resDir}/${fileName}" "${resDir}/${fileName}.mp3"
    fi
    resultLame="$?";
    if [ ! "$resultLame" = '0' ]; then
      echo "Can not convert file '${filePath}' to mp3. Lame status ${resultLame}";
    else
      rm -rf "${resDir}/${fileName}";
      chmod o+r "${resDir}/${fileName}.mp3";
    fi
  fi

  rm -rf "${resDir}/${fileName}";
  # delete first row
  sed -i'.bak' '1d' "${1}"; rm -rf "${1}.bak";
}

endProgramm ()
{
  echo
  echo $2
  exit $1
}

# $1 - path to file list
# $2 - host:port
if [ "n${1}" = 'n' ]; then
  endProgramm 1 "Path ro file list '\$1' is empty";
fi

if [ "n${2}" = 'n' ]; then
  endProgramm 2 "Host '\$1' is empty";
fi

if [ ! -f "$1" ]; then
  endProgramm 3 "File '$1' not exists";
fi

monDir='/storage/usbdisk1/mikopbx/voicemailarchive/monitor';
countRows=$(cat $1 | wc -l);
# countRows=$((countRows + 1));

i=1;
while : ; do
    if [ "$i" -gt "${countRows}" ]; then
        # Это установка системы. Меню необходимо отключить.
        endProgramm 0 'Download complete.';
    fi
    getFile "$@";
    i=$((i + 1));
done



