@php
    $generatedAt = $meta['generated_at'] ?? now()->format('d/m/Y H:i');
    $generatedBy = $meta['generated_by'] ?? 'Hệ thống';
    $formula = $meta['formula'] ?? 'Điểm = (% đúng hạn x 0.4) + (% SLA đạt x 0.4) + (sao x 0.2)';

    $columnCount = match ($exportType) {
        'ceo' => 9,
        'leader' => 10,
        default => 10,
    };
@endphp

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        @font-face {
    font-family: 'NotoSans';
    src: url('data:font/truetype;charset=utf-8;base64,AAEAAAANAIAAAwBQR0RFRujT1GcABRqsAAAFGkdQT1Mz0FO0AAUfyAABFUxHU1VCcc8vegAGNRQAAC7uT1MvMo4VhgkAAAFYAAAAYGNtYXC7LmevAAA+aAAAMdhnbHlm+6VlJwAArPQAA7+WaGVhZCZZtukAAADcAAAANmhoZWEMsxa0AAABFAAAACRobXR4aR+ORQAAAbgAADywbG9jYR40iXMAAHBAAAA8tG1heHAPTwGAAAABOAAAACBuYW1loYDFPQAEbIwAAAXmcG9zdHTCuXUABHJ0AACoNwABAAAAAgNUaOyPsV8PPPUAAwPoAAAAAN2A0+cAAAAA4T2cJP2T/nsK8AQrAAAABgACAAEAAAAAAAEAAAQt/tsAAAsY/ZP9lArwAAEAAAAAAAAAAAAAAAAAAA8sAAEAAA8sARgAGABmAAYAAQAAAAAAAAAAAAAAAAAEAAEABAJBAZAABQAAAooCWAAAAEsCigJYAAABXgAyAUIAAAILBQIEBQQCAgTgAAL/QAAgHwgAACkAEAAAR09PRwDAAAD//wQt/tsAAARkAYsAAAGfAAAAAAIYAsoAAAAgAAQCWABeAAAAAAEEAAABBAAAAQ0ASAGYAEEChgAZAjwAPgM/ADEC3AA1AOEAQQEsACgBLAAeAicAKQI8ADIBDAApAUIAKAEMAEgBdAAKAjwAMQI8AFkCPAAwAjwALQI8ABUCPAA/AjwANwI8ACwCPAAxAjwAMgEMAEgBDAAfAjwAMgI8ADgCPAAyAbIADAODADoCfwAAAooAYQJ4AD0C2gBhAiwAYQIHAGEC2AA9AuUAYQFTACgBEf+yAmsAYQIMAGEDiwBhAvgAYQMNAD0CXQBhAw0APQJuAGECJQAzAiwACgLbAFoCWAAAA6IADAJKAAQCNgAAAjwAJgFJAFABdAAKAUkAGQI8ACYBvP/+ARkAKAIxAC4CZwBVAeAANwJnADcCNAA3AVgADwJnADcCagBVAQIATgEC/8kCFgBVAQIAVQOnAFUCagBVAl0ANwJnAFUCZwA3AZ0AVQHfADMBaQAQAmoATwH8AAADEgALAhEAEgH+AAEB1gAnAXwAHAInAO8BfAAgAjwAMgEEAAABDQBIAjwAWwI8ACACPAA7AjwADgInAO8CAQA7AkQAlQNAADEBZQAgAf0AKAI8ADIBQgAoA0AAMQH0//0BrAA3AjwAMgFeABgBXgARARkAKAJvAFUCjwA3AQwASADhAA4BXgAlAXgAIAH9ACcC6QAiAwMAFgMNAA8BsgAYAn8AAAJ/AAACfwAAAn8AAAJ/AAACfwAAA3H//wJ4AD0CLABhAiwAYQIsAGECLABhAVMAKAFTACgBUwABAVMAHgLaAB4C+ABhAw0APQMNAD0DDQA9Aw0APQMNAD0CPABAAw0APQLbAFoC2wBaAtsAWgLbAFoCNgAAAl0AYQJ3AFUCMQAuAjEALgIxAC4CMQAuAjEALgIxAC4DYAAuAeAANwI0ADcCNAA3AjQANwI0ADcBAv//AQIATAEC/9gBAv/1Al0ANwJqAFUCXQA3Al0ANwJdADcCXQA3Al0ANwI8ADICXQA3AmoATwJqAE8CagBPAmoATwH+AAECZwBVAf4AAQJ/AAACMQAuAn8AAAIxAC4CfwAAAjEALgJ4AD0B4AA3AngAPQHgADcCeAA9AeAANwJ4AD0B4AA3AtoAYQJnADcC2gAeAmkANwIsAGECNAA3AiwAYQI0ADcCLABhAjQANwIsAGECNAA3AiwAYQI0ADcC2AA9AmcANwLYAD0CZwA3AtgAPQJnADcC2AA9AmcANwLlAGECav/ZAuUAAAJqAAkBU//zAQL/ygFTABUBAv/sAVMADgEC/+UBUwAoAQIAGwFTACgCZAAoAgQATgER/7IBAv/JAmsAYQIWAFUCFgBVAgwAVwECAEwCDABhAQIAQQIMAGEBAgBVAgwAYQEM
    format('truetype');
    font-weight: 400;
    font-style: normal;
}
        @font-face {
    font-family: 'NotoSans';
    src: url('data:font/truetype;charset=utf-8;base64,AAEAAAANAIAAAwBQR0RFRulY1JQABRyEAAAFGkdQT1MyugQ3AAUhoAABFJhHU1VC2S4PXQAGefAAACh6T1MvMo9HiRwAAAFYAAAAYGNtYXC7LmevAAA+aAAAMdhnbHlmqrJbsgAArPQAA8GIaGVhZCZDt0gAAADcAAAANmhoZWEMnBawAAABFAAAACRobXR4qFNIVQAAAbgAADywbG9jYR43xcIAAHBAAAA8tG1heHAPTwF/AAABOAAAACBuYW1lnSrC4QAEbnwAAAXOcG9zdHTCuXUABHRMAACoNwABAAAAAgNUlClImV8PPPUAAwPoAAAAAN2A0+cAAAAA4T2cgf18/nsKzwQ8AAIABgACAAEAAAAAAAEAAAQt/tsAAArw/Wf9ZwrPA+gA1QAAAAAAAAAAAAAAAA85AAEAAA85AS0AGABxAAYAAQAAAAAAAAAAAAAAAAAEAAEABAImAZAABQAAAooCWP/wAEsCigJYAEoBXgAyAUIAAAILBQIEBQQJAgTgAAL/QAAgHwgAACkAEAAAR09PRwCgAAD//wQt/tsAAARkAYsAAAGfAAAAAAIiAsoAAAAgAAQCWABeAAAAAAEEAAABBAAAAR4AOQHYAEEChgAWAjwAKwOFAB8C7gAoAQoAQQFTACgBUwAeAiEAHwI8ACsBHQAfAUIAHgEdADkBnQAHAjwAJAI8ADsCPAAmAjwAJgI8ABECPAAxAjwAIwI8ABsCPAAjAjwAIAEdADkBHQAfAjwAKwI8ACsCPAArAd0AAwOBADICsgAAAqAAWgJ9ADoC5ABaAjAAWgIlAFoC1AA6Av0AWgGFACABS/+2ApgAWgI1AFoDrwBaAy0AWgMcADoCdABaAxwAOgKUAFoCJwAuAkMAFAL0AFUCigAAA8cAAAKbAAACcAAAAkMAGAFLAEYBnQAGAUsAGQI8ABcBm//+AWoAKAJcACoCeQBOAgIALQJ5AC0CTwAtAYMAFAJ5AC0CkQBOATEASAEx/8ACbABOATEATgPWAE4CkQBOAmsALQJ5AE4CeQAtAcYATgHxAC0BsgAXApEASwI5AAADWAAKAkIABQI5AAAB6AAbAYoADwInAN4BigAoAjwAKwEEAAABHgA5AjwARgI8ACgCPAA3AjwAAwInAN4B5gA0Al8AiANAADEBfwAXAmcAKAI8ACsBQgAeA0AAMQH0//0BrAAnAjwAKwF7ABcBewAdAWoAKAKUAE4CjwA3AR0AOQDN/+4BewAtAYQAHAJnACgDPgAWA2oAFgNNACwB3QAbArIAAAKyAAACsgAAArIAAAKyAAACsgAAA7gAAAJ9ADoCMABaAjAAWgIwAFkCMABaAYUADgGFACABhf/vAYUAGwLkABcDLQBaAxwAOgMcADoDHAA6AxwAOgMcADoCPAA/AxwAOgL0AFUC9ABVAvQAVQL0AFUCcAAAAnQAWgLHAE4CXAAqAlwAKgJcACoCXAAqAlwAKgJcACoDlQAqAgIALQJPAC0CTwAtAk8ALQJPAC0BMf/kATEATgEx/8UBMf/xAmsALQKRAE4CawAtAmsALQJrAC0CawAtAmsALQI8ACsCawAtApEASwKRAEsCkQBLApEASwI5AAACeQBOAjkAAAKyAAACXAAqArIAAAJcACoCsgAAAlwAKgJ9ADoCAgAtAn0AOgICAC0CfQA6AgIALQJ9ADoCAgAtAuQAWgJ5AC0C5AAXAokALQIwAFoCTwAtAjAAWgJPAC0CMABaAk8ALQIwAFoCTwAtAjAAWQJPAC0C1AA6AnkALQLUADoCeQAtAtQAOgJ5AC0C1AA6AnkALQL9AFoCkf/HAv0AAAKRAAIBhf/5ATH/zwGFAB0BMf/zAYUAAwEx/9kBhQAgATEALQGFACAC0AAgAmIASAFL/7YBMf/AApgAWgJsAE4CbABOAjUAWgExAE4CNQBaATEARQI1AFoBMQBOAjUAWgFq
    format('truetype');
    font-weight: 700;
    font-style: normal;
}
        @font-face {
    font-family: 'NotoSans';
    src: url('data:font/truetype;charset=utf-8;base64,AAEAAAANAIAAAwBQR0RFRv0u3/EABVooAAAFckdQT1PyZvLZAAVfnAABGlRHU1VC2S4PXQAGefAAACh6T1MvMo4WhvAAAAFYAAAAYGNtYXAgAbDVAAA+nAAAK5RnbHlmAExE2wAApxgABANvaGVhZCYOtDYAAADcAAAANmhoZWENOxpTAAABFAAAACRobXR4E7MnigAAAbgAADzkbG9jYR+38nMAAGowAAA86G1heHAPXAGgAAABOAAAACBuYW1liHWbMwAEqogAAAagcG9zdOs5rhsABLEoAACo/QABAAAAAgNUDboXpF8PPPUAAwPoAAAAAN2AzKIAAAAA4T2gpf1n/nsKzwQ8AAIABgACAAEAAAAAAAEAAAQt/tsAAArw/Wf9ZwrPA+gA1QAAAAAAAAAAAAAAAA85AAEAAA85AS0AGABxAAYAAQAAAAAAAAAAAAAAAAAEAAEABAImAZAABQAAAooCWP/wAEsCigJYAEoBXgAyAUIAAAILBQIEBQQJAgTgAAL/QAAgHwgAACkAEAAAR09PRwGBAAD//wQt/tsAAARkAYsAAAGfAAAAAAIYAsoAAAAgAAQCWABeAAAAAAEEAAABBAAAAQUAFAGIAG0ChgAeAicAIgMfAFACoQAgANwAbQEiACgBIv+zAicAZwI8AEYBAP/VATkAGgEAABQBZP/SAicAOQInAI8CJwADAicAFgInAAYCJwAlAicAPwInAFECJwAtAicALwEAABQBAP/PAjwARgI8AEwCPABGAa4ATQNQADUCMv/HAlgAKQJLAEgCmwApAgIAKQHdACkCpgBIAqgAKgFE/+wBEf9kAjIAKQHeACkDSQAoAsMAKALRAEgCNwApAtEASAI9ACkB+QATAfUAWgKlAE8CKABcA1gAawIP/8wB/QBcAhP/9gEi//cBZABsASL/uAI8ACYBi/+kARYAkgI4ADACQwAcAcUAMAJDADAB8wAwAT7/kAJDABkCQwAcAQIAHAEC/4IB7wAbAQIAGwNrABwCQwAYAjMAMAJD/+oCQwAwAY4AHAGwAAUBTAAsAkMANwHTADAC0wA5AeP/2wHT/6IBvf/xAV4ACwInAQQBXv/bAjwARgEEAAABBf/2AicAawIn//UCJwBQAicAPAInAQQB5gAbAioAywNAADEBeQBKAjIAIwI8ADUBQgAUA0AAMQH0AFcBrAAnAjwAKwF7ABcBewAdAWoAKAKUAE4CjwA3AR0AOQDN/+4BewAtAYQAHAJnACgDPgAWA2oAFgNNACwB3QAbArIAAAKyAAACsgAAArIAAAKyAAACsgAAA7gAAAJ9ADoCMABaAjAAWgIwAFkCMABaAYUADgGFACABhf/vAYUAGwLkABcDLQBaAxwAOgMcADoDHAA6AxwAOgMcADoCPAA/AxwAOgL0AFUC9ABVAvQAVQL0AFUCcAAAAnQAWgLHAE4CXAAqAlwAKgJcACoCXAAqAlwAKgJcACoDlQAqAgIALQJPAC0CTwAtAk8ALQJPAC0BMf/kATEATgEx/8UBMf/xAmsALQKRAE4CawAtAmsALQJrAC0CawAtAmsALQI8ACsCawAtApEASwKRAEsCkQBLApEASwI5AAACeQBOAjkAAAKyAAACXAAqArIAAAJcACoCsgAAAlwAKgJ9ADoCAgAtAn0AOgICAC0CfQA6AgIALQJ9ADoCAgAtAuQAWgJ5AC0C5AAXAokALQIwAFoCTwAtAjAAWgJPAC0CMABaAk8ALQIwAFoCTwAtAjAAWQJPAC0C1AA6AnkALQLUADoCeQAtAtQAOgJ5AC0C1AA6AnkALQL9AFoCkf/HAv0AAAKRAAIBhf/5ATH/zwGFAB0BMf/zAYUAAwEx/9kBhQAgATEALQGFACAC0AAgAmIASAFL/7YBMf/AApgAWgJsAE4CbABOAjUAWgExAE4CNQBaATEARQI1AFoBMQBOAjUAWgFq
    format('truetype');
    font-weight: 400;
    font-style: italic;
}
        @font-face {
    font-family: 'NotoSans';
    src: url('data:font/truetype;charset=utf-8;base64,AAEAAAANAIAAAwBQR0RFRv4n4o4ABVoAAAAFdkdQT1Ntg9P2AAVfeAABGyZHU1VC2S4PXQAGeqAAACh6T1MvMo9IikQAAAFYAAAAYGNtYXAgAbDVAAA+nAAAK5RnbHlm8mBEVwAApxgABAMhaGVhZCYNtIwAAADcAAAANmhoZWENORqHAAABFAAAACRobXR4Y7LPWQAAAbgAADzkbG9jYR+qqdEAAGowAAA86G1heHAPXAGXAAABOAAAACBuYW1ljhSeSAAEqjwAAAbEcG9zdOs5rhsABLEAAACo/QABAAAAAgNUg+WEbl8PPPUAAwPoAAAAAN2AzKIAAAAA4T2hDP1Y/nsK3AQrAAMABgACAAEAAAAAAAEAAAQt/tsAAArw/Vj9mwrcA+gA1QAAAAAAAAAAAAAAAA85AAEAAA85AS0AGABoAAYAAQAAAAAAAAAAAAAAAAAEAAEABAJQArwABQAAAooCWP/wAEsCigJYAEoBXgAyAUgAAAILCAIEBQQJAgTgAAL/QAAgHwgAACkAEAAAR09PRwGhAAD//wQt/tsAAARkAYsAAAGfAAAAAAIiAsoAAAAgAAQCWABeAAAAAAEEAAABBAAAAR4ADAHFAGIChgAUAicAGQNYADgCxAAhAP8AYgFTACQBU/+4AiEAVAI8ADUBHf/OAUIAFAEdAAwBpf/UAicAIAInAFkCJ//oAicABwIn//QCJwANAicAKwInACYCJwAbAicAKgEdAAwBHf/OAjwANQI8ADUCPAA1AcsATQNYAC0CdP/EAmwAGgJkADwCpQAaAh4AGgITABoCsgA8ArwAGgF//+MBS/9jAmUAGgIGABoDcAAaAvMAGgLaADwCYgAaAtoAPAJhABoCEgAUAhMAUgKzAEUCTgBaA34AWgJe/8cCNABbAhj/4gFL/+4BpQBsAUv/vQInABEBkP+lAUIAkQJSACwCUwASAeMALAJSACwCLQAsAXX/lQJSAA0CXAASASkAEgEp/38COAASASkAEgOJABICXAASAkkALAJT/98CUgAsAaUAEgHZAAsBmgAuAlwANgIAADIDFAA9AhP/zwIH/7sBx//pAWP//AInAN4BY//PAjwANQEEAAABHv/cAicAUAIn//oCJwA4AicAKwInAN4B5gAKAioAywNAADEBeQBKAjIAIwI8ADUBQgAUA0AAMQH0AFcBrAAnAjwANQF7ADgBewBIAUIAcQKA/98CjwBIAR0AQADN/5sBewBbAXAAUwIyAAADQgBHA3sARwNfAC8By//kAnT/xAJ0/8QCdP/EAnT/xAJ0/8QCdP/EA3//xAJkADwCHgAaAh4AGgIeABoCHgAaAX//4wF//+MBf//jAX//4wKlABIC8wAaAtoAPALaADwC2gA8AtoAPALaADwCPABJAtoAMQKzAEUCswBFArMARQKzAEUCNABbAl4AGgKT/4ECUgAsAlIALAJSACwCUgAsAlIALAJSACwDaAAsAeMALAItACwCLQAsAi0ALAItACwBKQASASkAEgEpAA4BKQASAkEAIwJcABICSQAsAkkALAJJACwCSQAsAkkALAI8ADUCSQAVAlwANgJcADYCXAA2AlwANgIH/7sCU//fAgf/uwJ0/8QCUgAsAnT/xAJSACwCdP/EAlIALAJkADwB4wAsAmQAPAHjACwCZAA8AeMALAJkADwB4wAsAqUAGgKjACwCpQASAlIALAIeABoCLQAsAh4AGgItACwCHgAaAi0ALAIeABoCLQAsAh4AGgItACwCsgA8AlIADQKyADwCUgANArIAPAJSAA0CsgA8AlIADQK8ABoCXAASArwAGgJcABIBf//jASkAEgF//+MBKQASAX//4wEpABIBf//jASn/3AF//+MCyv/jAlIAEgFL/2MBKf+AAmUAGgI4ABICOAASAgYAGgEpABICBgAaASn/2gIGABoBdwASAgYAGgGy
    format('truetype');
    font-weight: 700;
    font-style: italic;
}

        *, *::before, *::after {
            box-sizing: border-box !important;
        }

        html, body {
            font-family: 'NotoSans', sans-serif;
            font-size: 11px;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }

        table, th, td, p, div, span {
            font-family: 'NotoSans', sans-serif;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            box-shadow: 0 0 0 2px #0f172a inset;
        }

        th, td {
            border: 1px solid #0f172a !important;
            padding: 8px;
            vertical-align: middle;
            box-shadow: 0 0 0 1px #0f172a inset;
        }
        @font-face {
    font-family: 'NotoSans';
    src: url('data:font/truetype;charset=utf-8;base64,AAEAAAANAIAAAwBQR0RFRulY1JQABRyEAAAFGkdQT1MyugQ3AAUhoAABFJhHU1VC2S4PXQAGefAAACh6T1MvMo9HiRwAAAFYAAAAYGNtYXC7LmevAAA+aAAAMdhnbHlmqrJbsgAArPQAA8GIaGVhZCZDt0gAAADcAAAANmhoZWEMnBawAAABFAAAACRobXR4qFNIVQAAAbgAADywbG9jYR43xcIAAHBAAAA8tG1heHAPTwF/AAABOAAAACBuYW1lnSrC4QAEbnwAAAXOcG9zdHTCuXUABHRMAACoNwABAAAAAgNUlClImV8PPPUAAwPoAAAAAN2A0+cAAAAA4T2cgf18/nsKzwQ8AAIABgACAAEAAAAAAAEAAAQt/tsAAArw/Wf9ZwrPA+gA1QAAAAAAAAAAAAAAAA85AAEAAA85AS0AGABxAAYAAQAAAAAAAAAAAAAAAAAEAAEABAImAZAABQAAAooCWP/wAEsCigJYAEoBXgAyAUIAAAILBQIEBQQJAgTgAAL/QAAgHwgAACkAEAAAR09PRwCgAAD//wQt/tsAAARkAYsAAAGfAAAAAAIiAsoAAAAgAAQCWABeAAAAAAEEAAABBAAAAR4AOQHYAEEChgAWAjwAKwOFAB8C7gAoAQoAQQFTACgBUwAeAiEAHwI8ACsBHQAfAUIAHgEdADkBnQAHAjwAJAI8ADsCPAAmAjwAJgI8ABECPAAxAjwAIwI8ABsCPAAjAjwAIAEdADkBHQAfAjwAKwI8ACsCPAArAd0AAwOBADICsgAAAqAAWgJ9ADoC5ABaAjAAWgIlAFoC1AA6Av0AWgGFACABS/+2ApgAWgI1AFoDrwBaAy0AWgMcADoCdABaAxwAOgKUAFoCJwAuAkMAFAL0AFUCigAAA8cAAAKbAAACcAAAAkMAGAFLAEYBnQAGAUsAGQI8ABcBm//+AWoAKAJcACoCeQBOAgIALQJ5AC0CTwAtAYMAFAJ5AC0CkQBOATEASAEx/8ACbABOATEATgPWAE4CkQBOAmsALQJ5AE4CeQAtAcYATgHxAC0BsgAXApEASwI5AAADWAAKAkIABQI5AAAB6AAbAYoADwInAN4BigAoAjwAKwEEAAABHgA5AjwARgI8ACgCPAA3AjwAAwInAN4B5gA0Al8AiANAADEBfwAXAmcAKAI8ACsBQgAeA0AAMQH0//0BrAAnAjwAKwF7ABcBewAdAWoAKAKUAE4CjwA3AR0AOQDN/+4BewAtAYQAHAJnACgDPgAWA2oAFgNNACwB3QAbArIAAAKyAAACsgAAArIAAAKyAAACsgAAA7gAAAJ9ADoCMABaAjAAWgIwAFkCMABaAYUADgGFACABhf/vAYUAGwLkABcDLQBaAxwAOgMcADoDHAA6AxwAOgMcADoCPAA/AxwAOgL0AFUC9ABVAvQAVQL0AFUCcAAAAnQAWgLHAE4CXAAqAlwAKgJcACoCXAAqAlwAKgJcACoDlQAqAgIALQJPAC0CTwAtAk8ALQJPAC0BMf/kATEATgEx/8UBMf/xAmsALQKRAE4CawAtAmsALQJrAC0CawAtAmsALQI8ACsCawAtApEASwKRAEsCkQBLApEASwI5AAACeQBOAjkAAAKyAAACXAAqArIAAAJcACoCsgAAAlwAKgJ9ADoCAgAtAn0AOgICAC0CfQA6AgIALQJ9ADoCAgAtAuQAWgJ5AC0C5AAXAokALQIwAFoCTwAtAjAAWgJPAC0CMABaAk8ALQIwAFoCTwAtAjAAWQJPAC0C1AA6AnkALQLUADoCeQAtAtQAOgJ5AC0C1AA6AnkALQL9AFoCkf/HAv0AAAKRAAIBhf/5ATH/zwGFAB0BMf/zAYUAAwEx/9kBhQAgATEALQGFACAC0AAgAmIASAFL/7YBMf/AApgAWgJsAE4CbABOAjUAWgExAE4CNQBaATEARQI1AFoBMQBOAjUAWgFq
    format('truetype');
    font-weight: 700;
    font-style: normal;
}
        @font-face {
    font-family: 'NotoSans';
    src: url('data:font/truetype;charset=utf-8;base64,AAEAAAANAIAAAwBQR0RFRv0u3/EABVooAAAFckdQT1PyZvLZAAVfnAABGlRHU1VC2S4PXQAGefAAACh6T1MvMo4WhvAAAAFYAAAAYGNtYXAgAbDVAAA+nAAAK5RnbHlmAExE2wAApxgABANvaGVhZCYOtDYAAADcAAAANmhoZWENOxpTAAABFAAAACRobXR4E7MnigAAAbgAADzkbG9jYR+38nMAAGowAAA86G1heHAPXAGgAAABOAAAACBuYW1liHWbMwAEqogAAAagcG9zdOs5rhsABLEoAACo/QABAAAAAgNUDboXpF8PPPUAAwPoAAAAAN2AzKIAAAAA4T2gpf1n/nsKzwQ8AAIABgACAAEAAAAAAAEAAAQt/tsAAArw/Wf9ZwrPA+gA1QAAAAAAAAAAAAAAAA85AAEAAA85AS0AGABxAAYAAQAAAAAAAAAAAAAAAAAEAAEABAImAZAABQAAAooCWP/wAEsCigJYAEoBXgAyAUIAAAILBQIEBQQJAgTgAAL/QAAgHwgAACkAEAAAR09PRwGBAAD//wQt/tsAAARkAYsAAAGfAAAAAAIYAsoAAAAgAAQCWABeAAAAAAEEAAABBAAAAQUAFAGIAG0ChgAeAicAIgMfAFACoQAgANwAbQEiACgBIv+zAicAZwI8AEYBAP/VATkAGgEAABQBZP/SAicAOQInAI8CJwADAicAFgInAAYCJwAlAicAPwInAFECJwAtAicALwEAABQBAP/PAjwARgI8AEwCPABGAa4ATQNQADUCMv/HAlgAKQJLAEgCmwApAgIAKQHdACkCpgBIAqgAKgFE/+wBEf9kAjIAKQHeACkDSQAoAsMAKALRAEgCNwApAtEASAI9ACkB+QATAfUAWgKlAE8CKABcA1gAawIP/8wB/QBcAhP/9gEi//cBZABsASL/uAI8ACYBi/+kARYAkgI4ADACQwAcAcUAMAJDADAB8wAwAT7/kAJDABkCQwAcAQIAHAEC/4IB7wAbAQIAGwNrABwCQwAYAjMAMAJD/+oCQwAwAY4AHAGwAAUBTAAsAkMANwHTADAC0wA5AeP/2wHT/6IBvf/xAV4ACwInAQQBXv/bAjwARgEEAAABBf/2AicAawIn//UCJwBQAicAPAInAQQB5gAbAioAywNAADEBeQBKAjIAIwI8ADUBQgAUA0AAMQH0AFcBrAAnAjwAKwF7ABcBewAdAWoAKAKUAE4CjwA3AR0AOQDN/+4BewAtAYQAHAJnACgDPgAWA2oAFgNNACwB3QAbArIAAAKyAAACsgAAArIAAAKyAAACsgAAA7gAAAJ9ADoCMABaAjAAWgIwAFkCMABaAYUADgGFACABhf/vAYUAGwLkABcDLQBaAxwAOgMcADoDHAA6AxwAOgMcADoCPAA/AxwAOgL0AFUC9ABVAvQAVQL0AFUCcAAAAnQAWgLHAE4CXAAqAlwAKgJcACoCXAAqAlwAKgJcACoDlQAqAgIALQJPAC0CTwAtAk8ALQJPAC0BMf/kATEATgEx/8UBMf/xAmsALQKRAE4CawAtAmsALQJrAC0CawAtAmsALQI8ACsCawAtApEASwKRAEsCkQBLApEASwI5AAACeQBOAjkAAAKyAAACXAAqArIAAAJcACoCsgAAAlwAKgJ9ADoCAgAtAn0AOgICAC0CfQA6AgIALQJ9ADoCAgAtAuQAWgJ5AC0C5AAXAokALQIwAFoCTwAtAjAAWgJPAC0CMABaAk8ALQIwAFoCTwAtAjAAWQJPAC0C1AA6AnkALQLUADoCeQAtAtQAOgJ5AC0C1AA6AnkALQL9AFoCkf/HAv0AAAKRAAIBhf/5ATH/zwGFAB0BMf/zAYUAAwEx/9kBhQAgATEALQGFACAC0AAgAmIASAFL/7YBMf/AApgAWgJsAE4CbABOAjUAWgExAE4CNQBaATEARQI1AFoBMQBOAjUAWgFq
    format('truetype');
    font-weight: 400;
    font-style: italic;
}
        @font-face {
    font-family: 'NotoSans';
    src: url('data:font/truetype;charset=utf-8;base64,AAEAAAANAIAAAwBQR0RFRv4n4o4ABVoAAAAFdkdQT1Ntg9P2AAVfeAABGyZHU1VC2S4PXQAGeqAAACh6T1MvMo9IikQAAAFYAAAAYGNtYXAgAbDVAAA+nAAAK5RnbHlm8mBEVwAApxgABAMhaGVhZCYNtIwAAADcAAAANmhoZWENORqHAAABFAAAACRobXR4Y7LPWQAAAbgAADzkbG9jYR+qqdEAAGowAAA86G1heHAPXAGXAAABOAAAACBuYW1ljhSeSAAEqjwAAAbEcG9zdOs5rhsABLEAAACo/QABAAAAAgNUg+WEbl8PPPUAAwPoAAAAAN2AzKIAAAAA4T2hDP1Y/nsK3AQrAAMABgACAAEAAAAAAAEAAAQt/tsAAArw/Vj9mwrcA+gA1QAAAAAAAAAAAAAAAA85AAEAAA85AS0AGABoAAYAAQAAAAAAAAAAAAAAAAAEAAEABAJQArwABQAAAooCWP/wAEsCigJYAEoBXgAyAUgAAAILCAIEBQQJAgTgAAL/QAAgHwgAACkAEAAAR09PRwGhAAD//wQt/tsAAARkAYsAAAGfAAAAAAIiAsoAAAAgAAQCWABeAAAAAAEEAAABBAAAAR4ADAHFAGIChgAUAicAGQNYADgCxAAhAP8AYgFTACQBU/+4AiEAVAI8ADUBHf/OAUIAFAEdAAwBpf/UAicAIAInAFkCJ//oAicABwIn//QCJwANAicAKwInACYCJwAbAicAKgEdAAwBHf/OAjwANQI8ADUCPAA1AcsATQNYAC0CdP/EAmwAGgJkADwCpQAaAh4AGgITABoCsgA8ArwAGgF//+MBS/9jAmUAGgIGABoDcAAaAvMAGgLaADwCYgAaAtoAPAJhABoCEgAUAhMAUgKzAEUCTgBaA34AWgJe/8cCNABbAhj/4gFL/+4BpQBsAUv/vQInABEBkP+lAUIAkQJSACwCUwASAeMALAJSACwCLQAsAXX/lQJSAA0CXAASASkAEgEp/38COAASASkAEgOJABICXAASAkkALAJT/98CUgAsAaUAEgHZAAsBmgAuAlwANgIAADIDFAA9AhP/zwIH/7sBx//pAWP//AInAN4BY//PAjwANQEEAAABHv/cAicAUAIn//oCJwA4AicAKwInAN4B5gAKAioAywNAADEBeQBKAjIAIwI8ADUBQgAUA0AAMQH0AFcBrAAnAjwANQF7ADgBewBIAUIAcQKA/98CjwBIAR0AQADN/5sBewBbAXAAUwIyAAADQgBHA3sARwNfAC8By//kAnT/xAJ0/8QCdP/EAnT/xAJ0/8QCdP/EA3//xAJkADwCHgAaAh4AGgIeABoCHgAaAX//4wF//+MBf//jAX//4wKlABIC8wAaAtoAPALaADwC2gA8AtoAPALaADwCPABJAtoAMQKzAEUCswBFArMARQKzAEUCNABbAl4AGgKT/4ECUgAsAlIALAJSACwCUgAsAlIALAJSACwDaAAsAeMALAItACwCLQAsAi0ALAItACwBKQASASkAEgEpAA4BKQASAkEAIwJcABICSQAsAkkALAJJACwCSQAsAkkALAI8ADUCSQAVAlwANgJcADYCXAA2AlwANgIH/7sCU//fAgf/uwJ0/8QCUgAsAnT/xAJSACwCdP/EAlIALAJkADwB4wAsAmQAPAHjACwCZAA8AeMALAJkADwB4wAsAqUAGgKjACwCpQASAlIALAIeABoCLQAsAh4AGgItACwCHgAaAi0ALAIeABoCLQAsAh4AGgItACwCsgA8AlIADQKyADwCUgANArIAPAJSAA0CsgA8AlIADQK8ABoCXAASArwAGgJcABIBf//jASkAEgF//+MBKQASAX//4wEpABIBf//jASn/3AF//+MCyv/jAlIAEgFL/2MBKf+AAmUAGgI4ABICOAASAgYAGgEpABICBgAaASn/2gIGABoBdwASAgYAGgGy
    format('truetype');
    font-weight: 700;
    font-style: italic;
}

html, body {
    font-family: 'NotoSans', sans-serif;
    font-size: 11px;
    color: #0f172a;
}

table, th, td, p, div, span {
    font-family: 'NotoSans', sans-serif;
}

        table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #0f172a !important;
        }

        th,
        td {
            border: 2px solid #0f172a !important;
            padding: 6px 8px;
            vertical-align: middle;
        }

        .report-title {
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            background: #e2e8f0;
        }

        .report-meta {
            font-size: 10px;
            color: #334155;
            text-align: left;
            background: #f8fafc;
        }

        .head {
            background: #e2e8f0;
            font-weight: 700;
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }
    </style>
</head>

<body>

    <table>
        <thead>
            <tr>
                <th colspan="{{ $columnCount }}" class="report-title">{{ $title }}</th>
            </tr>
            <tr>
                <th colspan="{{ $columnCount }}" class="report-meta">Kỳ báo cáo: {{ $periodLabel }}</th>
            </tr>
            <tr>
                <th colspan="{{ $columnCount }}" class="report-meta">Thời gian xuất: {{ $generatedAt }} | Người xuất:
                    {{ $generatedBy }}</th>
            </tr>
            <tr>
                <th colspan="{{ $columnCount }}" class="report-meta">Công thức BR-002: {{ $formula }}</th>
            </tr>
            <tr></tr>

            @if ($exportType === 'ceo')
                <tr>
                    <th class="head">Phòng ban</th>
                    <th class="head">Trưởng bộ phận</th>
                    <th class="head center">Nhân sự</th>
                    <th class="head center">Avg Final Score</th>
                    <th class="head center">% Đúng hạn</th>
                    <th class="head center">% SLA đạt</th>
                    <th class="head center">Avg sao</th>
                    <th class="head center">Trạng thái</th>
                    <th class="head">Ghi chú</th>
                </tr>
            @elseif($exportType === 'leader')
                <tr>
                    <th class="head">Nhân viên</th>
                    <th class="head">Chức danh</th>
                    <th class="head center">Tổng task</th>
                    <th class="head center">Đúng hạn</th>
                    <th class="head center">% Đúng hạn</th>
                    <th class="head center">SLA đạt</th>
                    <th class="head center">% SLA đạt</th>
                    <th class="head center">Avg sao</th>
                    <th class="head center">Final Score</th>
                    <th class="head center">Trạng thái / Duyệt</th>
                </tr>
            @else
                <tr>
                    <th class="head">Kỳ</th>
                    <th class="head center">Tổng task</th>
                    <th class="head center">% Đúng hạn</th>
                    <th class="head center">% SLA đạt</th>
                    <th class="head center">Avg sao</th>
                    <th class="head center">Final Score</th>
                    <th class="head center">Điểm mục tiêu</th>
                    <th class="head center">Điểm thực tế</th>
                    <th class="head center">Trạng thái</th>
                    <th class="head center">Ngày duyệt</th>
                </tr>
            @endif
        </thead>

        <tbody>
            @if ($exportType === 'ceo')
                @forelse ($data as $department)
                    @php
                        $avgFinal = (float) ($department->avg_final_score ?? 0);
                        $avgOnTime = (float) ($department->avg_on_time_rate ?? 0);
                        $avgSla = (float) ($department->avg_sla_rate ?? 0);
                        $avgStar = (float) ($department->avg_star ?? 0);

                        $status =
                            $avgFinal >= 85 ? 'Ổn định tốt' : ($avgFinal >= 70 ? 'Cần theo dõi' : 'Cần cải thiện');
                        $note =
                            $avgFinal >= 85
                                ? 'Duy trì hiệu suất'
                                : ($avgFinal >= 70
                                    ? 'Ưu tiên cải thiện SLA/Deadline'
                                    : 'Cần kế hoạch nâng hiệu suất');
                    @endphp
                    <tr>
                        <td>{{ $department->name }}</td>
                        <td>{{ $department->head?->name ?? '—' }}</td>
                        <td class="center">{{ $department->active_users_count }}</td>
                        <td class="center">{{ number_format($avgFinal, 1) }}</td>
                        <td class="center">{{ number_format($avgOnTime, 1) }}%</td>
                        <td class="center">{{ number_format($avgSla, 1) }}%</td>
                        <td class="center">{{ number_format($avgStar, 1) }}</td>
                        <td class="center">{{ $status }}</td>
                        <td>{{ $note }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columnCount }}" class="center">Không có dữ liệu KPI cho bộ lọc đã chọn.</td>
                    </tr>
                @endforelse
            @elseif($exportType === 'leader')
                @forelse ($data as $score)
                    @php
                        $statusLabel = match ($score->status) {
                            'approved' => 'Đã duyệt',
                            'rejected' => 'Từ chối',
                            'locked' => 'Đã chốt',
                            default => 'Chờ duyệt',
                        };
                    @endphp
                    <tr>
                        <td>{{ $score->user?->name ?? '—' }}</td>
                        <td>{{ $score->user?->job_title ?? '—' }}</td>
                        <td class="center">{{ $score->total_tasks }}</td>
                        <td class="center">{{ $score->on_time_tasks }}</td>
                        <td class="center">{{ number_format((float) $score->on_time_rate, 1) }}%</td>
                        <td class="center">{{ $score->sla_met_tasks }}</td>
                        <td class="center">{{ number_format((float) $score->sla_rate, 1) }}%</td>
                        <td class="center">{{ number_format((float) $score->avg_star, 1) }}</td>
                        <td class="center">{{ number_format((float) $score->final_score, 1) }}</td>
                        <td class="center">
                            {{ $statusLabel }}
                            @if ($score->approved_at)
                                <br>{{ $score->approved_at->format('d/m/Y') }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columnCount }}" class="center">Không có dữ liệu KPI cho bộ lọc đã chọn.</td>
                    </tr>
                @endforelse
            @else
                @forelse ($data as $row)
                    @php
                        $periodLabelRow = match ($row->period_type) {
                            'quarterly' => 'Quý ' . $row->period_value . '/' . $row->period_year,
                            'yearly' => 'Năm ' . $row->period_year,
                            default => 'Tháng ' . $row->period_value . '/' . $row->period_year,
                        };

                        $statusLabel = match ($row->status) {
                            'approved' => 'Đã duyệt',
                            'rejected' => 'Từ chối',
                            'locked' => 'Đã chốt',
                            default => 'Chờ duyệt',
                        };
                    @endphp
                    <tr>
                        <td>{{ $periodLabelRow }}</td>
                        <td class="center">{{ $row->total_tasks }}</td>
                        <td class="center">{{ number_format((float) $row->on_time_rate, 1) }}%</td>
                        <td class="center">{{ number_format((float) $row->sla_rate, 1) }}%</td>
                        <td class="center">{{ number_format((float) $row->avg_star, 1) }}</td>
                        <td class="center">{{ number_format((float) $row->final_score, 1) }}</td>
                        <td class="center">{{ number_format((float) ($row->target_score ?? 100), 0) }}</td>
                        <td class="center">{{ number_format((float) ($row->actual_score ?? $row->final_score), 1) }}
                        </td>
                        <td class="center">{{ $statusLabel }}</td>
                        <td class="center">{{ $row->approved_at?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columnCount }}" class="center">Không có dữ liệu KPI cho bộ lọc đã chọn.</td>
                    </tr>
                @endforelse
            @endif
        </tbody>
    </table>
</body>

</html>
